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

        $maxTerms = (int) Settings::get('knowledge.synonym_expansion_max_terms', 10);

        // Tokenize the query: split on whitespace and preserve quoted phrases.
        $tokens = $this->tokenize($query);

        // Collect expanded terms to append to the original query.
        // Typesense q parameter doesn't support boolean OR syntax — instead
        // we append synonym terms so Typesense ranks matches on any term.
        $originalTerms = [];
        $expansionTerms = [];

        foreach ($tokens as $token) {
            $normalized = mb_strtolower($token);
            $originalTerms[] = $token;

            if (isset($expansionMap[$normalized])) {
                $synonyms = $expansionMap[$normalized];

                // Cap the number of expanded terms to prevent query bloat.
                if (count($synonyms) > $maxTerms) {
                    $synonyms = array_slice($synonyms, 0, $maxTerms);
                }

                foreach ($synonyms as $synonym) {
                    $synLower = mb_strtolower($synonym);

                    // Don't add the original token as an expansion.
                    if ($synLower === $normalized) {
                        continue;
                    }

                    // Quote multi-word phrases.
                    if (str_contains($synonym, ' ')) {
                        $expansionTerms[] = '"'.$synonym.'"';
                    } else {
                        $expansionTerms[] = $synonym;
                    }
                }
            }
        }

        if ($expansionTerms === []) {
            return $query;
        }

        // Append unique expansion terms. The expanded query looks like:
        //   "deployment pipeline" release ship automation "continuous delivery"
        // Typesense matches documents containing any subset of these terms.
        // Note: Typesense's native TF-IDF scoring gives synonym terms equal
        // weight to original terms. The expansion cap (max_terms) is the primary
        // defense against synonym-rich documents dominating ranking — keep it low
        // (default 10) for precision, increase for recall-heavy deployments.
        $unique = array_unique($expansionTerms);

        return $query.' '.implode(' ', $unique);
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
