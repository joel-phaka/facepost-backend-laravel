<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $userId = User::all(['id'])->pluck('id')->random();
        $title = fake()->text(60);
        $content= fake()->realText(360);

        return [
            'user_id' => $userId,
            'title' => $title,
            'content' => $content,
        ];
    }
}
