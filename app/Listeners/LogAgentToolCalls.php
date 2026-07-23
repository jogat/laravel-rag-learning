<?php

namespace App\Listeners;

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

        Log::info('agent tool invoked', [
            'correlation_id' => Context::get('correlation_id'),
            'agent' => class_basename($event->agent),
            'tool' => class_basename($event->tool),
            'arguments' => $event->arguments,
            'result' => (string) $event->result,
        ]);
    }
}
