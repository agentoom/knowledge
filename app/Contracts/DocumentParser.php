<?php

namespace App\Contracts;

interface DocumentParser
{
    /**
     * @return array{content: string, metadata: array<string, mixed>}
     */
    public function parse(string $filePath): array;

    public function supports(string $mimeType): bool;
}
