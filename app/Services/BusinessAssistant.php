<?php

namespace App\Services;

use App\Ai\Agents\BusinessAgent;
use App\Ai\Agents\IntentClassifier;
use App\Ai\Agents\OrderSpecialist;
use App\Ai\Agents\ProductSpecialist;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BusinessAssistant
{
    public function __construct(private ConversationManager $conversations) {}

    public function ask(Project $project, User $user, string $question)
    {
        Context::add('correlation_id', (string) Str::uuid7());

        $intent = (new IntentClassifier)->prompt($question)['intent'] ?? 'out_of_scope';

        if ($intent === 'out_of_scope') {
            Log::info('assistant.out_of_scope', ['project' => $project->slug, 'user_id' => $user->id]);

            return ['reply' => $project->language->outOfScopeReply(), 'conversation_id' => null];
        }

        $agent = (new BusinessAgent(
            language: $project->language,
            think: false
        ))->setTools([
            new ProductSpecialist($project),
            new OrderSpecialist($project, $user),
        ]);

        $active = $this->conversations->activeConversationId($user, $project);

        $response = $active
            ? $agent->continue($active, as: $user)->prompt($question)
            : $agent->forUser($user)->prompt($question);

        $this->conversations->tagProject($response->conversationId, $project);

        $reply = $response->text;

        if ($this->leaksInternals($reply)) {
            Log::warning('assistant.prompt_leak', ['conversation_id' => $response->conversationId]);
            $reply = $project->language->outOfScopeReply();
            $this->conversations->redactLastReply($response->conversationId, $reply);
        }

        return [
            'reply' => $reply,
            'conversation_id' => $response->conversationId,
        ];
    }

    private function leaksInternals(string $reply): bool
    {
        return Str::contains($reply, [
            BusinessAgent::CANARY,
            'product_specialist',
            'order_specialist',
            'QueryOrder',
            'SimilaritySearch',
        ], ignoreCase: true);
    }
}
