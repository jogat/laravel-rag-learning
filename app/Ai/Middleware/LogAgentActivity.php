<?php

namespace App\Ai\Middleware;

use Closure;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Gateway\StepResult;
use Laravel\Ai\PendingStep;

/**
 * Logs every generation step (one model round trip) of an agent.
 *
 * Since laravel/ai 1.0 middleware wraps each step, not the whole run: an orchestrator that calls a
 * sub-agent logs one line for the step that requested the tool and another for the step that answered.
 * Time spent inside the tool (the sub-agent) falls between those steps and is not part of either.
 */
class LogAgentActivity
{
    public function __construct(private string $agent) {}

    /**
     * Handle the incoming generation step.
     */
    public function handle(PendingStep $step, Closure $next): StepResult
    {
        $start = microtime(true);

        return $next($step)->then(function (StepResponse $response) use ($step, $start) {
            Log::info('agent step', [
                'correlation_id' => Context::get('correlation_id'),
                'agent' => $this->agent,
                'invocation_id' => $step->invocationId,
                'step' => $step->number,
                'response' => Str::limit($response->text, 150),
                'tool_calls' => count($response->toolCalls),
                'latency_ms' => round((microtime(true) - $start) * 1000),
                'input_tokens' => $response->usage->inputTokens,
                'output_tokens' => $response->usage->outputTokens,
                'reasoning_tokens' => $response->usage->reasoningTokens,  // ← the "thinking" cost, per step
            ]);
        });
    }
}
