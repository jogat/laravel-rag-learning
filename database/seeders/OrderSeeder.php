<?php

namespace Database\Seeders;

use App\Enums\OrderStatusEnum;
use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $alice = User::where('email', 'test@example.com')->first();      // the caller we'll simulate
        $bob   = User::factory()->create(['email' => 'bob@example.com']);

        // Mismo número de pedido en dos negocios distintos: prueba el aislamiento por project_id.
        Order::create([
            'project_id' => Project::where('slug', 'demo-es')->value('id'),
            'user_id' => $alice->id,
            'order_number' => '12345',
            'status' => OrderStatusEnum::ON_ITS_WAY,
            'estimated_delivery_date' => now()->addDays(3),
            'items' => ['Auriculares inalámbricos', 'Cargador USB-C'],
        ]);

        Order::create([
            'project_id' => Project::where('slug', 'demo-en')->value('id'),
            'user_id' => $bob->id,
            'order_number' => '12345',
            'status' => OrderStatusEnum::DELIVERED,
            'estimated_delivery_date' => now()->subDays(2),
            'items' => ['Yoga mat', 'Water bottle'],
        ]);
    }
}
