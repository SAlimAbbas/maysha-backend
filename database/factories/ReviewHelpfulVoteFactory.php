<?php

namespace Database\Factories;

use App\Models\Review;
use App\Models\ReviewHelpfulVote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReviewHelpfulVoteFactory extends Factory
{
    protected $model = ReviewHelpfulVote::class;

    public function definition(): array
    {
        return [
            'review_id' => Review::factory(),
            'user_id' => User::factory(),
            'ip' => fake()->ipv4(),
        ];
    }
}
