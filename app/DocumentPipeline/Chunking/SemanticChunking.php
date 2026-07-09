<?php

namespace App\DocumentPipeline\Chunking;

use App\Contracts\ChunkingStrategy;

/**
 * Chunks text by respecting paragraph and sentence boundaries.
 *
 * Priority order: paragraph breaks > sentence breaks > word breaks.
 * Produces chunks that are semantically coherent rather than
 * arbitrarily cut at character positions.
 */
class SemanticChunking implements ChunkingStrategy
{
    public function __construct(
        private readonly int $maxChunkSize = 1500,
        private readonly int $minChunkSize = 200,
    ) {}

    public function chunk(string $content, array $options = []): array
    {
        $maxSize = $options['max_chunk_size'] ?? $this->maxChunkSize;
        $minSize = $options['min_chunk_size'] ?? $this->minChunkSize;

        $content = $this->normalizeWhitespace($content);

        if (strlen($content) <= $maxSize) {
            return [trim($content)];
        }

        $paragraphs = $this->splitParagraphs($content);

        return $this->mergeParagraphsToChunks($paragraphs, $maxSize, $minSize);
    }

    public function name(): string
    {
        return 'semantic';
    }

    private function normalizeWhitespace(string $text): string
    {
        return preg_replace('/[ \t]+/', ' ', $text) ?? $text;
    }

    /**
     * @return array<int, string>
     */
    private function splitParagraphs(string $content): array
    {
        $blocks = preg_split('/\n\s*\n/', $content) ?: [$content];

        $paragraphs = [];

        foreach ($blocks as $block) {
            $trimmed = trim($block);

            if ($trimmed === '' || $trimmed === '0') {
                continue;
            }

            if (strlen($trimmed) <= $this->maxChunkSize) {
                $paragraphs[] = $trimmed;
            } else {
                $sentences = $this->splitSentences($trimmed);
                $paragraphs = array_merge($paragraphs, $sentences);
            }
        }

        return $paragraphs;
    }

    /**
     * @return array<int, string>
     */
    private function splitSentences(string $paragraph): array
    {
        $sentences = preg_split('/(?<=[.!?])\s+/', $paragraph, -1, PREG_SPLIT_NO_EMPTY) ?: [$paragraph];

        $result = [];

        foreach ($sentences as $sentence) {
            $trimmed = trim($sentence);

            if ($trimmed === '' || $trimmed === '0') {
                continue;
            }

            if (strlen($trimmed) > $this->maxChunkSize) {
                $result = array_merge($result, $this->forceSplit($trimmed, $this->maxChunkSize));
            } else {
                $result[] = $trimmed;
            }
        }

        return $result;
    }

    /**
     * @param  array<int, string>  $paragraphs
     * @return array<int, string>
     */
    private function mergeParagraphsToChunks(array $paragraphs, int $maxSize, int $minSize): array
    {
        $chunks = [];
        $current = '';

        foreach ($paragraphs as $paragraph) {
            if ($current === '' || $current === '0') {
                $current = $paragraph;

                continue;
            }

            $combined = $current."\n\n".$paragraph;

            if (strlen($combined) <= $maxSize) {
                $current = $combined;
            } else {
                if (strlen($current) >= $minSize) {
                    $chunks[] = $current;
                    $current = $paragraph;
                } else {
                    $chunks[] = $current;
                    $current = $paragraph;
                }
            }
        }

        if ($current !== '' && $current !== '0') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * Last resort: split at word boundaries within max size.
     *
     * @return array<int, string>
     */
    private function forceSplit(string $text, int $maxSize): array
    {
        $chunks = [];
        $words = explode(' ', $text);
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;

            if (strlen($candidate) <= $maxSize) {
                $current = $candidate;
            } else {
                if ($current !== '' && $current !== '0') {
                    $chunks[] = $current;
                }

                $current = $word;
            }
        }

        if ($current !== '' && $current !== '0') {
            $chunks[] = $current;
        }

        return $chunks;
    }
}
