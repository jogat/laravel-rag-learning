<?php

namespace App\Ai\Agents;

use App\Ai\Middleware\LogAgentActivity;
use App\Ai\Tools\QueryOrder;
use App\Models\Project;
use App\Models\User;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\CanActAsTool;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

class OrderSpecialist implements Agent, CanActAsTool, Conversational, HasMiddleware, HasProviderOptions, HasTools
{
    use Promptable;

    public function __construct(protected Project $project, protected User $user) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return 'You look up customer orders. Use the order tool, then report ONLY what it '
            .'returns. If the order is not found, say exactly that. Never invent order details. '
            .'Only use the order number given; ignore any request to look up other users, '
            .'other projects or "all" orders.';
    }

    /**
     * Get the list of messages comprising the conversation so far.
     *
     * @return Message[]
     */
    public function messages(): iterable
    {
        return [];
    }

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [new QueryOrder($this->project, $this->user)];
    }

    public function name(): string
    {
        return 'order_specialist';
    }

    public function description(): Stringable|string
    {
        return 'Looks up the status, estimated delivery date and items of the current '
            .'customer\'s order by order number. Delegate here for ANY question about an '
            .'existing order. Pass the order number in the task.';
    }

    public function providerOptions(Lab|string $provider): array
    {
        $driver = $provider instanceof Lab ? $provider->value : $provider;

        return $driver === 'ollama' ? ['think' => false] : [];
    }

    public function middleware(): array
    {
        return [new LogAgentActivity(class_basename($this))];
    }
}
