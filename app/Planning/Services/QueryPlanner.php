<?php

namespace App\Planning\Services;

use App\Contracts\PlannerStrategy;
use App\Planning\Strategies\DefaultPlanner;
use App\Retrieval\Models\ExecutionPlan;
use App\Retrieval\Models\SearchQuery;

class QueryPlanner
{
    private PlannerStrategy $strategy;

    public function __construct(?PlannerStrategy $strategy = null)
    {
        $this->strategy = $strategy ?? app(DefaultPlanner::class);
    }

    public function plan(SearchQuery $query): ExecutionPlan
    {
        return $this->strategy->plan($query);
    }

    public function setStrategy(PlannerStrategy $strategy): void
    {
        $this->strategy = $strategy;
    }

    public function getStrategy(): PlannerStrategy
    {
        return $this->strategy;
    }
}
