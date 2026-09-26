<?php

namespace Database\Factories;

use App\Models\NewsAttachment;
use App\Models\NewsPost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsAttachment>
 */
class NewsAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'news_post_id' => NewsPost::factory(),
            'path' => 'news-attachments/'.fake()->uuid().'.png',
            'name' => fake()->word().'.png',
            'mime_type' => 'image/png',
            'size' => fake()->numberBetween(10_000, 2_000_000),
        ];
    }

    /**
     * Indicate that the file is a video rather than an image.
     */
    public function video(): static
    {
        return $this->state(fn (array $attributes) => [
            'path' => 'news-attachments/'.fake()->uuid().'.mp4',
            'name' => fake()->word().'.mp4',
            'mime_type' => 'video/mp4',
            'size' => fake()->numberBetween(1_000_000, 40_000_000),
        ]);
    }
}
