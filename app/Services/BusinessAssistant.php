<?php

namespace App\Services;

use App\Ai\Agents\BusinessAgent;
use App\Ai\Agents\IntentClassifier;
use App\Ai\Tools\QueryOrder;
use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;

class BusinessAssistant
{
    /** Hidden Context stack where LogAgentToolCalls records each tool call while CHAT_DEBUG is on. */
    public const string TOOL_TRACE = 'assistant.tool_calls';

    public function __construct(
        private ConversationManager $conversations,
        private OrderReference $orderReference,
        private KnowledgeEvidence $knowledgeEvidence,
    ) {}

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

        $active = $this->conversations->activeConversationId($user, $project);
        $earlierQuestions = $active === null ? [] : (new BusinessAgent(language: $project->language, think: false))
            ->continue($active, as: $user)->previousCustomerMessages();
        $background = implode("\n", $earlierQuestions);
        $isFollowUp = preg_match('/^(?:¿?y\b|and\b|et\b)|\b(ese|esa|eso|that|those|cela)\b/iu', trim($question)) === 1
            || Str::wordCount($question) <= 3;
        $classificationQuestion = $background === '' || ! $isFollowUp ? $question
            : $background."\nUse the background only to resolve missing context in the current message. Classify ONLY the following current message:\n".$question;
        $intent = (new IntentClassifier)->prompt($classificationQuestion)['intent'] ?? 'out_of_scope';
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

        if ($intent !== 'out_of_scope' && $this->orderReference->isOrderQuestion($question, $earlierQuestions)) {
            $intent = 'order';
        }

        $agent = (new BusinessAgent(
            language: $project->language,
            think: false,
            intent: $intent,
        ));

        $agent->continueOrStart($active, as: $user);

        if ($intent === 'product' || ($intent === 'out_of_scope' && $bypassClassifier)) {
            $retrievalStartedAt = microtime(true);
            $previousQuestion = $earlierQuestions === [] ? '' : $earlierQuestions[array_key_last($earlierQuestions)];
            $searchQuery = $previousQuestion !== '' && $isFollowUp
                ? $previousQuestion."\nCurrent question: ".$question
                : $question;
            $agent->setQuestionContext($isFollowUp ? $previousQuestion : '');
            $excerpts = Document::query()
                ->where('project_id', $project->id)
                ->whereVectorSimilarTo('embedding', $searchQuery, minSimilarity: 0.4)
                ->limit(2)
                ->pluck('content')
                ->all();
            $referenceData = $this->knowledgeEvidence->forQuestion($excerpts, $question);
            $agent->setReferenceData($referenceData);

            if (config('assistant.debug')) {
                Context::pushHidden(self::TOOL_TRACE, [
                    'agent' => 'BusinessAssistant',
                    'tool' => 'SimilaritySearch',
                    'arguments' => ['query' => $searchQuery],
                    'result' => $referenceData,
                    'duration_ms' => round((microtime(true) - $retrievalStartedAt) * 1000),
                ]);
            }
        }

        if ($intent === 'order') {
            $orderNumber = $this->orderReference->resolve($question, $earlierQuestions);
            $orderStartedAt = microtime(true);
            $referenceData = $orderNumber === null
                ? 'No unambiguous order number was provided. Ask the customer for the order number.'
                : (new QueryOrder($project, $user))->handle(new Request(['order_number' => $orderNumber]));
            $agent->setReferenceData($referenceData);

            if ($orderNumber !== null && config('assistant.debug')) {
                Context::pushHidden(self::TOOL_TRACE, [
                    'agent' => 'BusinessAssistant',
                    'tool' => 'QueryOrder',
                    'arguments' => ['order_number' => $orderNumber],
                    'result' => $referenceData,
                    'duration_ms' => round((microtime(true) - $orderStartedAt) * 1000),
                ]);
            }
        }

        $response = $agent->prompt($question);

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
            'REFERENCE_DATA',
            'REFERENCE DATA:',
            '<think>',
            '</think>',
        ], ignoreCase: true);
    }
}
