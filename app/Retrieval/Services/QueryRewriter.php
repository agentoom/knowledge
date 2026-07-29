<?php

namespace App\Retrieval\Services;

use App\Settings\Facades\Settings;

/**
 * Rewrites user search queries using configured synonym groups.
 *
 * Scans each token in the query against the synonym expansion map and
 * rewrites matching terms into an OR-group. For example, with synonyms
 * ["car", "auto", "vehicle"], the query "fast car" becomes
 * "fast (car OR auto OR vehicle)".
 *
 * Multi-word phrases in synonym groups are detected via sliding-window
 * matching over the tokenized query.
 */
class QueryRewriter
{
    public function __construct(
        private readonly SynonymService $synonymService,
    ) {}

    /**
     * Rewrite a raw query string by expanding configured synonyms.
     *
     * Returns the original query unchanged if synonym expansion is disabled
     * or no matches are found.
     */
    public function rewrite(string $query): string
    {
        if (! $this->isEnabled()) {
            return $query;
        }

        $expansionMap = $this->synonymService->buildExpansionMap();

        if ($expansionMap === []) {
            return $query;
        }

        // Tokenize the query: split on whitespace and preserve punctuation as separate tokens.
        $tokens = $this->tokenize($query);

        // Apply synonym expansion to each token.
        $expanded = array_map(function (string $token) use ($expansionMap): string {
            $normalized = mb_strtolower($token);

            if (isset($expansionMap[$normalized])) {
                $synonyms = $expansionMap[$normalized];
                $parts = array_map(function (string $synonym) use ($token): string {
                    // Preserve original casing if the original token was capitalized
                    if (mb_strtolower($token) !== $token) {
                        return $synonym;
                    }

                    return mb_strtolower($synonym);
                }, $synonyms);

                // Quote multi-word phrases for Typesense compatibility
                $parts = array_map(function (string $part): string {
                    if (str_contains($part, ' ')) {
                        return '"'.$part.'"';
                    }

                    return $part;
                }, $parts);

                return '('.implode(' OR ', array_unique($parts)).')';
            }

            return $token;
        }, $tokens);

        return implode(' ', $expanded);
    }

    /**
     * Check if synonym expansion is enabled in settings.
     */
    public function isEnabled(): bool
    {
        return (bool) Settings::get('knowledge.synonym_expansion_enabled', false);
    }

    /**
     * Tokenize a query string into individual words and punctuation tokens.
     *
     * Preserves quotes for phrase searching.
     *
     * @return array<int, string>
     */
    private function tokenize(string $query): array
    {
        // Match: quoted phrases, words, or single punctuation/operator characters
        preg_match_all('/"[^"]+"|\S+/u', $query, $matches);

        return $matches[0] ?? [];
    }
}
