<?php

namespace App\DocumentPipeline\Chunking;

use App\Contracts\ChunkingStrategy;

class FixedSizeChunking implements ChunkingStrategy
{
    public function __construct(
        private readonly int $chunkSize = 1000,
    ) {}

    public function chunk(string $content, array $options = []): array
    {
        $size = $options['chunk_size'] ?? $this->chunkSize;

        if (strlen($content) <= $size) {
            return [trim($content)];
        }

        $chunks = [];
        $length = strlen($content);
        $start = 0;

        while ($start < $length) {
            $end = min($start + $size, $length);

            if ($end < $length) {
                $lastSpace = strrpos(substr($content, $start, $end - $start), ' ');
                if ($lastSpace !== false && $lastSpace > 0) {
                    $end = $start + $lastSpace;
                }
            }

            $chunks[] = trim(substr($content, $start, $end - $start));
            $start = $end;
        }

        return $chunks;
    }

    public function name(): string
    {
        return 'fixed_size';
    }
}
