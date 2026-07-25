<?php

namespace App\Retrieval\Fusion;

use App\Contracts\ResultFusionStrategy;
use Illuminate\Support\Facades\Log;

class ReciprocalRankFusion implements ResultFusionStrategy
{
    public function fuse(array $results): array
    {
        $scores = [];
        $lookup = [];

        // Single pass: compute RRF scores and build key→item lookup map.
        // First occurrence wins primary fields; subsequent occurrences merge metadata.
        foreach ($results as $resultIndex => $result) {
            if (! isset($result->items) || ! is_array($result->items)) {
                Log::warning('RRF: skipping invalid result.', [
                    'index' => $resultIndex,
                ]);

                continue;
            }

            foreach ($result->items as $rank => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $key = $item['id'] ?? md5(json_encode($item));
                $scores[$key] = ($scores[$key] ?? 0) + 1.0 / ($rank + 60);

                if (! isset($lookup[$key])) {
                    $lookup[$key] = $item;
                } else {
                    // Duplicate ID detected — merge metadata from the duplicate into
                    // the first occurrence so federation-source tags and other metadata
                    // are preserved across providers.
                    Log::warning('RRF: duplicate document ID detected; merging metadata.', [
                        'id' => $key,
                        'result_index' => $resultIndex,
                    ]);

                    $this->mergeItemMetadata($lookup[$key], $item);
                }
            }
        }

        arsort($scores);

        $fused = [];

        foreach (array_keys($scores) as $key) {
            $fused[] = $lookup[$key];
        }

        return $fused;
    }

    /**
     * Merge metadata from a duplicate item into the existing lookup entry.
     *
     * Scalar fields from the first occurrence are preserved. Any array-valued
     * keys (e.g., _federation_source tags added by multiple servers) are
     * merged so nothing is lost.
     *
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $duplicate
     */
    private function mergeItemMetadata(array &$existing, array $duplicate): void
    {
        foreach ($duplicate as $dupKey => $dupValue) {
            // Skip the id key itself — already matched
            if ($dupKey === 'id') {
                continue;
            }

            if (is_array($dupValue) && isset($existing[$dupKey]) && is_array($existing[$dupKey])) {
                $existing[$dupKey] = array_merge($existing[$dupKey], $dupValue);
            } elseif (! array_key_exists($dupKey, $existing)) {
                $existing[$dupKey] = $dupValue;
            }
        }
    }

    public function name(): string
    {
        return 'reciprocal_rank_fusion';
    }
}
