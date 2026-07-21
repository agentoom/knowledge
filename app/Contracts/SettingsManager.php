<?php

namespace App\Contracts;

interface SettingsManager
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value, string $type = 'string'): void;

    public function forget(string $key): void;

    /**
     * @return array<string, mixed>
     */
    public function all(string $group): array;

    public function isUsingFallback(): bool;
}
