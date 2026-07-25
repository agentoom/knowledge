<?php

namespace Database\Factories;

use App\Knowledge\Models\KnowledgeSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeSource>
 */
class KnowledgeSourceFactory extends Factory
{
    protected $model = KnowledgeSource::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $providerTypes = ['filesystem', 'sql', 'yaml', 'json'];

        return [
            'name' => fake()->unique()->words(3, true),
            'slug' => fn (array $attrs) => str($attrs['name'])->slug(),
            'description' => fake()->sentence(),
            'provider_type' => fake()->randomElement($providerTypes),
            'provider_config' => [],
            'namespace' => fake()->word(),
            'is_active' => true,
            'priority' => fake()->numberBetween(1, 100),
            'config_version' => 1,
        ];
    }
}
