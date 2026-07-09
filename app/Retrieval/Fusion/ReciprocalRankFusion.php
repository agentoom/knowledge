<?php

namespace App\Retrieval\Fusion;

use App\Contracts\ResultFusionStrategy;
use Illuminate\Support\Facades\Log;

class ReciprocalRankFusion implements ResultFusionStrategy
{
    public function fuse(array $results): array
    {
        $scores = [];

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
            }
        }

        arsort($scores);

        $fused = [];
        $seenKeys = [];

        foreach (array_keys($scores) as $key) {
            foreach ($results as $result) {
                if (! isset($result->items) || ! is_array($result->items)) {
                    continue;
                }

                foreach ($result->items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $itemKey = $item['id'] ?? md5(json_encode($item));
                    if ($itemKey === $key && ! in_array($key, $seenKeys, true)) {
                        $fused[] = $item;
                        $seenKeys[] = $key;
                        break 2;
                    }
                }
            }
        }

        return $fused;
    }

    public function name(): string
    {
        return 'reciprocal_rank_fusion';
    }
}
