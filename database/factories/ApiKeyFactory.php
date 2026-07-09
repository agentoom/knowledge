<?php

namespace Database\Factories;

use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<ApiKey>
 */
class ApiKeyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plainKey = 'test-key-'.bin2hex(random_bytes(16));

        return [
            'user_id' => User::factory(),
            'name' => fake()->word().' API Key',
            'key' => Hash::make($plainKey),
            'key_prefix' => substr($plainKey, 0, 8),
            'scopes' => ['mcp:use'],
            'last_used_at' => null,
            'expires_at' => null,
        ];
    }

    public function serviceAccount(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'scopes' => ['mcp:use', 'admin:*'],
        ]);
    }
}
