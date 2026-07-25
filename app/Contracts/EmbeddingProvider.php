<?php

namespace App\Contracts;

interface EmbeddingProvider
{
    /**
     * @return array<int, float>
     */
    public function embed(string $text): array;

    public function dimensions(): int;
}
