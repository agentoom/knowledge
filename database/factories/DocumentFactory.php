<?php

namespace Database\Factories;

use App\Knowledge\Models\Document;
use App\Knowledge\Models\KnowledgeSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $statuses = ['discovered', 'parsed', 'normalized', 'chunked', 'indexed', 'error'];

        return [
            'knowledge_source_id' => KnowledgeSource::factory(),
            'path' => '/data/'.fake()->word().'/'.fake()->word().'.'.fake()->fileExtension(),
            'filename' => fake()->word().'.'.fake()->fileExtension(),
            'mime_type' => fake()->mimeType(),
            'size_bytes' => fake()->numberBetween(100, 100000),
            'content' => fake()->paragraphs(3, true),
            'status' => fake()->randomElement($statuses),
            'metadata' => [],
        ];
    }
}
