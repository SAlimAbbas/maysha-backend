<?php

namespace Database\Factories;

use App\Models\Pincode;
use Illuminate\Database\Eloquent\Factories\Factory;

class PincodeFactory extends Factory
{
    protected $model = Pincode::class;

    public function definition(): array
    {
        return [
            'pincode' => (string) fake()->unique()->numberBetween(110001, 899999),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'serviceable' => true,
            'cod_available' => true,
            'eta_days' => fake()->numberBetween(2, 5),
        ];
    }
}
