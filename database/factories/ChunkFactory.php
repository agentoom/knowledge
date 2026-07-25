<?php

namespace Database\Factories;

use App\Knowledge\Models\Chunk;
use App\Knowledge\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chunk>
 */
class ChunkFactory extends Factory
{
    private static int $sequence = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static::$sequence++;

        return [
            'document_id' => Document::factory(),
            'sequence' => static::$sequence,
            'content' => fake()->paragraph(),
            'token_count' => fake()->numberBetween(50, 500),
            'embedding_hash' => md5(fake()->uuid()),
            'metadata' => [],
        ];
    }
}
