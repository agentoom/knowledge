<?php

namespace Database\Factories;

use App\VectorStore\Models\VectorStore;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VectorStore>
 */
class VectorStoreFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'driver' => 'typesense',
            'config' => [
                'host' => 'typesense',
                'port' => 8108,
                'protocol' => 'http',
                'api_key' => 'xyz',
            ],
            'is_active' => true,
        ];
    }
}
