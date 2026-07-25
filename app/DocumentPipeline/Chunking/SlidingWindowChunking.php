<?php

namespace App\DocumentPipeline\Chunking;

use App\Contracts\ChunkingStrategy;

/**
 * Produces overlapping chunks for context preservation across boundaries.
 *
 * Each chunk overlaps with the previous one by a configurable amount,
 * ensuring that information near chunk edges is never lost.
 */
class SlidingWindowChunking implements ChunkingStrategy
{
    public function __construct(
        private readonly int $windowSize = 1200,
        private readonly int $stride = 800,
    ) {}

    public function chunk(string $content, array $options = []): array
    {
        $windowSize = $options['window_size'] ?? $this->windowSize;
        $stride = $options['stride'] ?? $this->stride;

        if ($stride >= $windowSize) {
            $stride = max(1, (int) ($windowSize * 0.75));
        }

        $content = $this->normalizeWhitespace($content);

        if (strlen($content) <= $windowSize) {
            return [trim($content)];
        }

        $chunks = [];
        $length = strlen($content);
        $start = 0;

        while ($start < $length) {
            $end = min($start + $windowSize, $length);

            if ($end < $length) {
                $segment = substr($content, $start, $end - $start);
                $lastSpace = strrpos($segment, ' ');

                if ($lastSpace !== false && $lastSpace > 0) {
                    $end = $start + $lastSpace;
                }
            }

            $chunk = trim(substr($content, $start, $end - $start));

            if ($chunk !== '' && $chunk !== '0') {
                $chunks[] = $chunk;
            }

            $start += $stride;
        }

        return $chunks;
    }

    public function name(): string
    {
        return 'sliding_window';
    }

    private function normalizeWhitespace(string $text): string
    {
        return preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
    }
}
