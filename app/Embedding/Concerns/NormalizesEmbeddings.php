<?php

namespace App\Embedding\Concerns;

use RuntimeException;

/**
 * Shared helpers for normalizing raw provider responses into float vectors.
 */
trait NormalizesEmbeddings
{
    /**
     * Cast an arbitrary numeric list into `array<int, float>`.
     *
     * @return array<int, float>
     */
    protected function toFloatVector(mixed $values): array
    {
        if (! is_array($values) || $values === []) {
            throw new RuntimeException('Embedding response is missing the vector data.');
        }

        $vector = [];

        foreach ($values as $value) {
            if (! is_int($value) && ! is_float($value) && ! (is_string($value) && is_numeric($value))) {
                throw new RuntimeException('Embedding response contains a non-numeric vector component.');
            }

            $vector[] = (float) $value;
        }

        if ($vector === []) {
            throw new RuntimeException('Embedding response returned an empty vector.');
        }

        return $vector;
    }
}
