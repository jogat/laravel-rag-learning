<?php

namespace Database\Factories;

use App\Enums\OrderStatusEnum;
use App\Models\Order;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'order_number' => (string) fake()->unique()->numberBetween(10000, 99999),
            'user_id' => null,
            'status' => fake()->randomElement(OrderStatusEnum::cases()),
            'estimated_delivery_date' => now()->addDays(fake()->numberBetween(1, 7)),
            'items' => fake()->words(2),
        ];
    }
}
