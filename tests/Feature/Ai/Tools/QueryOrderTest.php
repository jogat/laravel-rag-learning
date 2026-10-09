<?php

use App\Ai\Tools\QueryOrder;
use App\Enums\OrderStatusEnum;
use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use Laravel\Ai\Tools\Request;

it('returns the order of the user in the project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    Order::factory()->for($project)->for($user, 'customer')->create([
        'order_number' => '12345',
        'status' => OrderStatusEnum::ON_ITS_WAY,
        'estimated_delivery_date' => '2026-10-12',
        'items' => ['Auriculares inalámbricos'],
    ]);

    $result = (new QueryOrder($project, $user))->handle(new Request(['order_number' => ' 12345 ']));

    expect((string) $result)->toBe('{"order_number":"12345","status":"on_its_way","estimated_delivery_date":"2026-10-12","items":["Auriculares inalámbricos"]}');
});

it('does not return an order with the same number from another project', function () {
    $user = User::factory()->create();
    Order::factory()->for(Project::factory())->for($user, 'customer')->create(['order_number' => '12345']);

    $result = (new QueryOrder(Project::factory()->create(), $user))->handle(new Request(['order_number' => '12345']));

    expect((string) $result)->toBe('Order `12345` not found.');
});

it('does not return an order that belongs to another user', function () {
    $project = Project::factory()->create();
    Order::factory()->for($project)->for(User::factory(), 'customer')->create(['order_number' => '12345']);

    $result = (new QueryOrder($project, User::factory()->create()))->handle(new Request(['order_number' => '12345']));

    expect((string) $result)->toBe('Order `12345` not found.');
});

it('rejects an order number that is not alphanumeric', function (string $orderNumber) {
    $result = (new QueryOrder(Project::factory()->create(), User::factory()->create()))
        ->handle(new Request(['order_number' => $orderNumber]));

    expect((string) $result)->toBe('Invalid order number. Please provide a valid order number.');
})->with([
    'empty' => '',
    'wildcard' => '%',
    'sql injection' => "1' OR '1'='1",
]);
