<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;

class RefundFactory extends Factory
{
    protected $model = Refund::class;

    public function definition(): array
    {
        return [
            'payment_id' => null,
            'order_id' => Order::factory(),
            'amount_paise' => fake()->numberBetween(10000, 50000),
            'reason' => 'Customer requested cancellation',
            'status' => 'pending',
            'gateway_refund_id' => 'rfnd_'.fake()->lexify('????????????'),
        ];
    }
}
