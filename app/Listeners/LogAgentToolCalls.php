<?php

namespace App\Listeners;

use App\Services\BusinessAssistant;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Events\ToolInvoked;

class LogAgentToolCalls
{
    /**
     * Handle the event.
     */
    public function handle(ToolInvoked $event): void
    {
        $call = [
            'agent' => class_basename($event->agent),
            'tool' => method_exists($event->tool, 'name') ? $event->tool->name() : class_basename($event->tool),
            'arguments' => $event->arguments,
            'result' => (string) $event->result,
            'duration_ms' => round($event->time),
        ];

        Log::info('agent tool invoked', [
            'correlation_id' => Context::get('correlation_id'),
            ...$call,
        ]);

        if (config('assistant.debug')) {
            Context::pushHidden(BusinessAssistant::TOOL_TRACE, $call);
        }
    }
}
