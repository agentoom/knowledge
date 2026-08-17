<?php

namespace App\DocumentPipeline\Services;

/**
 * Deterministic UTF-8 token counter used to keep document chunks within the
 * embedding/context window without depending on an external tokenizer.
 *
 * Each word/number run and each non-whitespace punctuation run counts as a
 * single token. Byte offsets are returned so callers can split the original
 * text verbatim (no normalization, no reserialized punctuation).
 */
class TokenCounter
{
    private const TOKEN_PATTERN = '/\p{L}[\p{L}\p{N}_\'-]*|\p{N}+|[^\s\p{L}\p{N}]+/u';

    public function count(string $text): int
    {
        return count($this->tokens($text));
    }

    /**
     * @return array<int, array{start: int, end: int}>
     */
    public function tokens(string $text): array
    {
        if ($text === '') {
            return [];
        }

        preg_match_all(self::TOKEN_PATTERN, $text, $matches, PREG_OFFSET_CAPTURE);

        return array_map(fn (array $match): array => [
            'start' => $match[1],
            'end' => $match[1] + strlen($match[0]),
        ], $matches[0] ?? []);
    }
}
