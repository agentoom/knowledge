<?php

namespace App\Contracts;

interface ChunkingStrategy
{
    /**
     * @param  array<string, mixed>  $options
     * @return array<int, string>
     */
    public function chunk(string $content, array $options = []): array;

    public function name(): string;
}
