<?php

use App\Models\SynonymGroup;
use App\Retrieval\Services\SynonymService;

beforeEach(function () {
    SynonymGroup::query()->delete();
    $this->service = app(SynonymService::class);
});

test('creates a synonym group', function () {
    $group = $this->service->create(['car', 'automobile', 'vehicle']);

    expect($group)->toBeInstanceOf(SynonymGroup::class)
        ->and($group->words)->toBe(['car', 'automobile', 'vehicle']);
});

test('throws when creating a group with fewer than 2 words', function () {
    expect(fn () => $this->service->create(['single']))
        ->toThrow(InvalidArgumentException::class, 'at least two words');
});

test('updates a synonym group', function () {
    $group = $this->service->create(['a', 'b']);
    $this->service->update($group->id, ['x', 'y', 'z']);

    $updated = SynonymGroup::find($group->id);
    expect($updated->words)->toBe(['x', 'y', 'z']);
});

test('throws when updating with fewer than 2 words', function () {
    $group = $this->service->create(['a', 'b']);

    expect(fn () => $this->service->update($group->id, ['single']))
        ->toThrow(InvalidArgumentException::class, 'at least two words');
});

test('deletes a synonym group', function () {
    $group = $this->service->create(['a', 'b']);
    $this->service->delete($group->id);

    expect(SynonymGroup::find($group->id))->toBeNull();
});

test('normalizes and deduplicates words', function () {
    $group = $this->service->create(['  car ', ' CAR ', 'automobile', 'car  ']);

    expect($group->words)->toBe(['car', 'automobile']);
});

test('builds expansion map from all groups', function () {
    $this->service->create(['car', 'auto']);
    $this->service->create(['fast', 'quick']);

    $map = $this->service->buildExpansionMap();

    expect($map)->toHaveKey('car')
        ->and($map)->toHaveKey('auto')
        ->and($map)->toHaveKey('fast')
        ->and($map)->toHaveKey('quick')
        ->and($map['car'])->toBe(['car', 'auto'])
        ->and($map['fast'])->toBe(['fast', 'quick']);
});

test('all returns all groups as word arrays', function () {
    $this->service->create(['a', 'b']);
    $this->service->create(['c', 'd']);

    $groups = $this->service->all();

    expect($groups)->toHaveCount(2)
        ->and($groups->first())->toBe(['a', 'b']);
});
