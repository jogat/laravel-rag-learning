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
    /** Hidden Context stack where LogAgentToolCalls records each tool call while CHAT_DEBUG is on. */
    public const string TOOL_TRACE = 'assistant.tool_calls';

    public function __construct(private ConversationManager $conversations) {}

    /**
     * Answer one chat message for a user in a project.
     *
     * With CHAT_BYPASS_INTENT_CLASSIFIER an "out_of_scope" verdict is logged but no longer blocks the
     * question. With CHAT_DEBUG the result carries a `debug` trace for auditing replies.
     *
     * @return array{reply: string, conversation_id: ?string, debug?: array<string, mixed>}
     */
    public function ask(Project $project, User $user, string $question): array
    {
        Context::add('correlation_id', (string) Str::uuid7());
        Context::forgetHidden(self::TOOL_TRACE);

        $intent = (new IntentClassifier)->prompt($question)['intent'] ?? 'out_of_scope';
        $bypassClassifier = (bool) config('assistant.bypass_intent_classifier');

        if ($intent === 'out_of_scope') {
            Log::info($bypassClassifier ? 'assistant.out_of_scope_bypassed' : 'assistant.out_of_scope', [
                'project' => $project->slug,
                'user_id' => $user->id,
            ]);

            if (! $bypassClassifier) {
                return $this->withDebug(
                    ['reply' => $project->language->outOfScopeReply(), 'conversation_id' => null],
                    $this->trace($intent, $bypassClassifier, blocked: true),
                );
            }
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
        $leaked = $this->leaksInternals($reply);

        if ($leaked) {
            Log::warning('assistant.prompt_leak', ['conversation_id' => $response->conversationId]);
            $reply = $project->language->outOfScopeReply();
            $this->conversations->redactLastReply($response->conversationId, $reply);
        }

        return $this->withDebug(
            ['reply' => $reply, 'conversation_id' => $response->conversationId],
            $this->trace($intent, $bypassClassifier, blocked: false, resumedConversationId: $active, unredactedReply: $leaked ? $response->text : null),
        );
    }

    /**
     * Build the audit trace for one turn.
     *
     * @return array{intent: string, intent_classifier_bypassed: bool, blocked_by_intent_classifier: bool, resumed_conversation_id: ?string, tool_calls: list<array<string, mixed>>, leak_detected: bool, unredacted_reply: ?string}
     */
    private function trace(string $intent, bool $bypassed, bool $blocked, ?string $resumedConversationId = null, ?string $unredactedReply = null): array
    {
        return [
            'intent' => $intent,
            'intent_classifier_bypassed' => $bypassed,
            'blocked_by_intent_classifier' => $blocked,
            'resumed_conversation_id' => $resumedConversationId,
            'tool_calls' => Context::getHidden(self::TOOL_TRACE, []),
            'leak_detected' => $unredactedReply !== null,
            'unredacted_reply' => $unredactedReply,
        ];
    }

    /**
     * Attach the debug trace only when CHAT_DEBUG is on.
     *
     * @param  array{reply: string, conversation_id: ?string}  $result
     * @param  array<string, mixed>  $trace
     * @return array{reply: string, conversation_id: ?string, debug?: array<string, mixed>}
     */
    private function withDebug(array $result, array $trace): array
    {
        return config('assistant.debug') ? [...$result, 'debug' => $trace] : $result;
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
