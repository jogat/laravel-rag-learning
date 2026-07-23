<?php

namespace App\Ai\Tools;

use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class QueryOrder implements Tool
{
    public function __construct(protected Project $project, protected User $user)
    {
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Query the status of an order, estimated delivery date and items from an order number. Use this whenever the user asks for an order.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $orderNumber = trim($request->string('order_number'));

        if ($orderNumber === '' || ! ctype_alnum($orderNumber)) {
            return 'Invalid order number. Please provide a valid order number.';
        }

        $order = Order::where('project_id', $this->project->id)
            ->where('order_number', $orderNumber)
            ->where('user_id', $this->user->id)
            ->first();

        if (! $order) {
            return "Order `$orderNumber` not found.";
        }

        return json_encode([
            'order_number' => $order->order_number,
            'status' => $order->status->value,
            'estimated_delivery_date' => $order->estimated_delivery_date->toDateString(),
            'items' => $order->items,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'order_number' => $schema->string()->required()->description('The order number from customer, example: 123456789'),
        ];
    }
}
