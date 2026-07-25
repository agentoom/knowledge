<?php

namespace Database\Factories;

use App\Knowledge\Models\KnowledgeSource;
use App\Knowledge\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Provider>
 */
class ProviderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'knowledge_source_id' => KnowledgeSource::factory(),
            'class' => 'App\\Providers\\Filesystem\\FilesystemProvider',
            'name' => fake()->word(),
            'type' => 'filesystem',
            'metadata' => [
                'namespace' => fake()->word(),
                'capabilities' => ['search', 'list_resources'],
            ],
            'status' => 'active',
            'last_synced_at' => now(),
        ];
    }
}
