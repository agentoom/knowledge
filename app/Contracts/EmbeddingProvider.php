<?php

namespace App\Contracts;

interface EmbeddingProvider
{
    public function embed(string $text): array;

    public function dimensions(): int;
}
