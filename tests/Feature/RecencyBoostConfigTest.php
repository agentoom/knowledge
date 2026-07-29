<?php

use App\Retrieval\Fusion\RecencyBoostConfig;

test('computes full boost for brand-new content', function () {
    $config = new RecencyBoostConfig(boostFactor: 0.3, halfLifeDays: 30.0);

    $multiplier = $config->computeMultiplier(0);

    expect($multiplier)->toBe(1.3); // 1.0 + 0.3 * exp(0) = 1.3
});

test('computes half boost at half-life', function () {
    $config = new RecencyBoostConfig(boostFactor: 0.4, halfLifeDays: 30.0);

    $multiplier = $config->computeMultiplier(30.0);

    // At half-life: 1.0 + 0.4 * 0.5 = 1.2
    expect($multiplier)->toEqualWithDelta(1.2, 0.0001);
});

test('approaches neutral for very old content', function () {
    $config = new RecencyBoostConfig(boostFactor: 0.5, halfLifeDays: 30.0);

    $multiplier = $config->computeMultiplier(365.0);

    // After a year, boost should be very close to 1.0
    expect($multiplier)->toBeGreaterThan(1.0)
        ->toBeLessThan(1.001);
});

test('isEnabled returns false when boostFactor is zero', function () {
    $config = new RecencyBoostConfig(boostFactor: 0.0, halfLifeDays: 30.0);

    expect($config->isEnabled())->toBeFalse();
});

test('isEnabled returns true when boostFactor is positive', function () {
    $config = new RecencyBoostConfig(boostFactor: 0.2, halfLifeDays: 30.0);

    expect($config->isEnabled())->toBeTrue();
});

test('computeMultiplier returns 1.0 when disabled', function () {
    $config = new RecencyBoostConfig(boostFactor: 0.0, halfLifeDays: 30.0);

    expect($config->computeMultiplier(0))->toBe(1.0);
});

test('lambda computes decay constant correctly', function () {
    $config = new RecencyBoostConfig(boostFactor: 0.3, halfLifeDays: 30.0);

    // λ = ln(2) / 30
    expect($config->lambda())->toEqualWithDelta(log(2) / 30, 0.0001);
});

test('throws for negative boostFactor', function () {
    expect(fn () => new RecencyBoostConfig(boostFactor: -0.1, halfLifeDays: 30.0))
        ->toThrow(InvalidArgumentException::class);
});

test('throws for boostFactor above 1.0', function () {
    expect(fn () => new RecencyBoostConfig(boostFactor: 1.5, halfLifeDays: 30.0))
        ->toThrow(InvalidArgumentException::class);
});

test('throws for non-positive halfLifeDays', function () {
    expect(fn () => new RecencyBoostConfig(boostFactor: 0.3, halfLifeDays: 0.0))
        ->toThrow(InvalidArgumentException::class);
});
