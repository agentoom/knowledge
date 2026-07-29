<?php

namespace App\Retrieval\Fusion;

readonly class RecencyBoostConfig
{
    /**
     * @param  float  $boostFactor  Multiplier applied to the recency signal (0.0–1.0). 0 = disabled.
     * @param  float  $halfLifeDays  Days after which the boost decays to half its original value.
     */
    public function __construct(
        public float $boostFactor = 0.3,
        public float $halfLifeDays = 30.0,
    ) {
        if ($boostFactor < 0.0 || $boostFactor > 1.0) {
            throw new \InvalidArgumentException('boostFactor must be between 0.0 and 1.0.');
        }

        if ($halfLifeDays <= 0.0) {
            throw new \InvalidArgumentException('halfLifeDays must be positive.');
        }
    }

    public function isEnabled(): bool
    {
        return $this->boostFactor > 0.0;
    }

    /**
     * Compute the decay constant λ = ln(2) / half-life.
     */
    public function lambda(): float
    {
        return log(2) / $this->halfLifeDays;
    }

    /**
     * Compute the recency multiplier for a given age in days.
     *
     * Formula: 1 + boostFactor * exp(-λ * daysSinceIndexed)
     *
     * - Brand-new content (0 days) gets the full boost: 1 + boostFactor
     * - Content at half-life gets: 1 + boostFactor * 0.5
     * - Very old content approaches: 1.0 (no penalty, just no bonus)
     */
    public function computeMultiplier(float $daysSinceIndexed): float
    {
        if (! $this->isEnabled()) {
            return 1.0;
        }

        return 1.0 + $this->boostFactor * exp(-$this->lambda() * $daysSinceIndexed);
    }
}
