<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderEventFactory extends Factory
{
    protected $model = OrderEvent::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'status' => 'processing',
            'note' => fake()->sentence(),
            'actor_id' => null,
        ];
    }
}
