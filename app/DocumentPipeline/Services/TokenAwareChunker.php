<?php

namespace App\DocumentPipeline\Services;

use App\Contracts\ChunkingStrategy;
use InvalidArgumentException;

/**
 * Enforces a token ceiling on top of the existing character-based chunking
 * strategies.
 *
 * The resolved strategy runs first so Markdown headings, paragraph/sentence
 * boundaries, and sliding-window overlap are retained. Base chunks already
 * within the limit pass through untouched; only oversized chunks are split at
 * token boundaries with the configured token overlap.
 */
class TokenAwareChunker
{
    public function __construct(private readonly TokenCounter $counter) {}

    /**
     * @return array<int, string>
     */
    public function chunk(ChunkingStrategy $strategy, string $content, int $maxTokens, int $overlapTokens): array
    {
        if ($maxTokens < 1) {
            throw new InvalidArgumentException('maxTokens must be at least 1.');
        }

        if ($overlapTokens < 0) {
            throw new InvalidArgumentException('overlapTokens must not be negative.');
        }

        if ($overlapTokens >= $maxTokens) {
            throw new InvalidArgumentException('overlapTokens must be smaller than maxTokens.');
        }

        if ($content === '') {
            return [];
        }

        $chunks = [];

        foreach ($strategy->chunk($content) as $baseChunk) {
            if ($baseChunk === '') {
                continue;
            }

            // Tokenize once per base chunk: the same token list feeds the cap
            // check and (when needed) the token-boundary split.
            $tokens = $this->counter->tokens($baseChunk);

            if (count($tokens) <= $maxTokens) {
                $chunks[] = $baseChunk;

                continue;
            }

            array_push($chunks, ...$this->splitOversized($baseChunk, $tokens, $maxTokens, $overlapTokens));
        }

        return $chunks;
    }

    /**
     * @param  array<int, array{start: int, end: int}>  $tokens
     * @return array<int, string>
     */
    private function splitOversized(string $baseChunk, array $tokens, int $maxTokens, int $overlapTokens): array
    {
        $chunks = [];
        $total = count($tokens);
        $start = 0;

        while ($start < $total) {
            $end = min($start + $maxTokens, $total);

            $chunkStart = $tokens[$start]['start'];
            $chunkEnd = $tokens[$end - 1]['end'];

            $chunks[] = substr($baseChunk, $chunkStart, $chunkEnd - $chunkStart);

            if ($end >= $total) {
                break;
            }

            // Forward progress is guaranteed because overlap < maxTokens:
            // the next window always starts strictly after the current one.
            $start = max($end - $overlapTokens, $start + 1);
        }

        return $chunks;
    }
}
