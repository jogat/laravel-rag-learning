<?php

namespace App\Ai\Middleware;

use Closure;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Prompts\AgentPrompt;

class LogAgentActivity
{
    /**
     * Handle the incoming prompt.
     */
    public function handle(AgentPrompt $prompt, Closure $next)
    {
        $start = microtime(true);
        $response = $next($prompt);                       // runs the agent (and, for the orchestrator, its sub-agents)
        $latencyMs = round((microtime(true) - $start) * 1000);

        Log::info('agent turn', [
            'correlation_id'    => Context::get('correlation_id'),
            'agent'             => class_basename($prompt->agent),
            'invocation_id'     => $response->invocationId,
            'prompt'            => Str::limit($prompt->prompt, 150),
            'response'          => Str::limit($response->text, 150),
            'latency_ms'        => $latencyMs,
            'prompt_tokens'     => $response->usage->promptTokens,
            'completion_tokens' => $response->usage->completionTokens,
            'reasoning_tokens'  => $response->usage->reasoningTokens,  // ← the "thinking" cost, per agent
        ]);

        return $response;
    }
}
