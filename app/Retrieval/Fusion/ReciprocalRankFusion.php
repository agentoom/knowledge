<?php

namespace App\Retrieval\Fusion;

use App\Contracts\ResultFusionStrategy;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ReciprocalRankFusion implements ResultFusionStrategy
{
    public function fuse(array $results, ?RecencyBoostConfig $recencyConfig = null): array
    {
        $scores = [];
        $lookup = [];

        $now = now();

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

        // Apply recency boost to each item's score before sorting.
        if ($recencyConfig !== null && $recencyConfig->isEnabled()) {
            foreach ($scores as $key => $score) {
                $daysSinceIndexed = $this->resolveDaysSinceIndexed($lookup[$key], $now);
                $multiplier = $recencyConfig->computeMultiplier($daysSinceIndexed);
                $scores[$key] = $score * $multiplier;
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
     * Extract the age of an item in days from its indexed_at or _timestamp field.
     *
     * Returns PHP_FLOAT_MAX for items without a timestamp so they get
     * a multiplier of effectively 1.0 (neutral).
     */
    private function resolveDaysSinceIndexed(array $item, CarbonInterface $now): float
    {
        $timestamp = $item['indexed_at'] ?? $item['_timestamp'] ?? null;

        if ($timestamp === null) {
            return PHP_FLOAT_MAX;
        }

        try {
            if (is_numeric($timestamp)) {
                $indexedAt = Carbon::createFromTimestamp((int) $timestamp);
            } else {
                $indexedAt = Carbon::parse($timestamp);
            }

            $days = abs($indexedAt->diffInDays($now));

            return (float) $days;
        } catch (\Throwable) {
            return PHP_FLOAT_MAX;
        }
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
