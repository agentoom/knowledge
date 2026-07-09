<?php

namespace App\DocumentPipeline\Chunking;

use App\Contracts\ChunkingStrategy;

class MarkdownChunking implements ChunkingStrategy
{
    public function __construct(
        private readonly int $maxChunkSize = 2000,
    ) {}

    public function chunk(string $content, array $options = []): array
    {
        $maxSize = $options['max_chunk_size'] ?? $this->maxChunkSize;

        if (strlen($content) <= $maxSize) {
            return [trim($content)];
        }

        $sections = preg_split('/\n(?=#{1,6}\s)/', $content);

        if ($sections === false || count($sections) <= 1) {
            return $this->fixedSizeChunk($content, $maxSize);
        }

        $chunks = [];

        foreach ($sections as $section) {
            $trimmed = trim($section);

            if ($trimmed === '' || $trimmed === '0') {
                continue;
            }

            if (strlen($trimmed) <= $maxSize) {
                $chunks[] = $trimmed;
            } else {
                $subChunks = $this->fixedSizeChunk($trimmed, $maxSize);
                $chunks = array_merge($chunks, $subChunks);
            }
        }

        return $chunks;
    }

    public function name(): string
    {
        return 'markdown';
    }

    /**
     * @return array<int, string>
     */
    private function fixedSizeChunk(string $content, int $maxSize): array
    {
        $chunks = [];
        $length = strlen($content);
        $start = 0;

        while ($start < $length) {
            $end = min($start + $maxSize, $length);

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
}
