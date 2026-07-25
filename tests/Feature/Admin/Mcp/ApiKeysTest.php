<?php

use App\Livewire\Admin\Mcp\ApiKeys;
use App\Models\ApiKey;
use App\Models\User;
use Livewire\Livewire;

test('it can create an api key with expiration', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $expiresAt = now()->addDays(30)->startOfDay();

    Livewire::test(ApiKeys::class)
        ->set('name', 'Test API Key')
        ->set('scopes', ['knowledge:read'])
        ->set('expiresAt', $expiresAt->format('Y-m-d'))
        ->call('create')
        ->assertHasNoErrors();

    $apiKey = ApiKey::where('name', 'Test API Key')->first();

    expect($apiKey)->not->toBeNull()
        ->and($apiKey->expires_at->startOfDay()->isSameDay($expiresAt))->toBeTrue()
        ->and($apiKey->user_id)->toBe($user->id);
});

test('it can create an api key without expiration', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(ApiKeys::class)
        ->set('name', 'Never Expiring Key')
        ->set('scopes', ['knowledge:read'])
        ->set('expiresAt', null)
        ->call('create')
        ->assertHasNoErrors();

    $apiKey = ApiKey::where('name', 'Never Expiring Key')->first();

    expect($apiKey)->not->toBeNull()
        ->and($apiKey->expires_at)->toBeNull();
});

test('it validates expiration date is in the future', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(ApiKeys::class)
        ->set('name', 'Invalid Date Key')
        ->set('scopes', ['knowledge:read'])
        ->set('expiresAt', now()->subDay()->format('Y-m-d'))
        ->call('create')
        ->assertHasErrors(['expiresAt' => 'after']);
});
