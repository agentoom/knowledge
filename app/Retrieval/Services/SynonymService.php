<?php

namespace App\Retrieval\Services;

use App\Models\SynonymGroup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Manages synonym groups for query expansion.
 *
 * Synonym groups are stored in the local database and can optionally be
 * synced to Typesense's native synonym API for server-side expansion.
 */
class SynonymService
{
    /**
     * Get all synonym groups as a collection of word arrays.
     *
     * @return Collection<int, array<int, string>>
     */
    public function all(): Collection
    {
        return SynonymGroup::orderBy('id')
            ->pluck('words')
            ->map(fn ($words) => is_array($words) ? $words : []);
    }

    /**
     * Create a new synonym group.
     *
     * @param  array<int, string>  $words
     */
    public function create(array $words): SynonymGroup
    {
        $words = $this->normalizeWords($words);

        if (count($words) < 2) {
            throw new \InvalidArgumentException('A synonym group must contain at least two words.');
        }

        return SynonymGroup::create(['words' => $words]);
    }

    /**
     * Update an existing synonym group.
     *
     * @param  array<int, string>  $words
     */
    public function update(int $id, array $words): SynonymGroup
    {
        $words = $this->normalizeWords($words);

        if (count($words) < 2) {
            throw new \InvalidArgumentException('A synonym group must contain at least two words.');
        }

        $group = SynonymGroup::findOrFail($id);
        $group->update(['words' => $words]);

        return $group;
    }

    public function delete(int $id): void
    {
        SynonymGroup::findOrFail($id)->delete();
    }

    /**
     * Sync all synonym groups to Typesense's native synonym API.
     *
     * This is optional — synonym expansion works at query time via the
     * QueryRewriter even without syncing to Typesense. Sync is useful
     * when you want Typesense to handle expansion server-side.
     */
    public function syncToTypesense(string $collection): void
    {
        Log::info('SynonymService: Typesense synonym sync is not yet implemented.');

        // Future: POST /collections/{collection}/synonyms with upsert for each group
    }

    /**
     * Build a lookup map: word → expanded set (all terms in its group).
     *
     * @return array<string, array<int, string>>
     */
    public function buildExpansionMap(): array
    {
        $map = [];

        foreach ($this->all() as $words) {
            foreach ($words as $word) {
                $normalized = mb_strtolower(trim($word));
                $map[$normalized] = $words;
            }
        }

        return $map;
    }

    /**
     * Normalize and deduplicate a list of words.
     *
     * @param  array<int, string>  $words
     * @return array<int, string>
     */
    private function normalizeWords(array $words): array
    {
        $normalized = [];
        $seen = [];

        foreach ($words as $word) {
            $word = trim($word);

            if ($word === '' || $word === '0') {
                continue;
            }

            $key = mb_strtolower($word);

            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $normalized[] = $word;
            }
        }

        return $normalized;
    }
}
