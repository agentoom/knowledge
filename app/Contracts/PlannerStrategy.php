<?php

namespace App\Contracts;

use App\Retrieval\Models\ExecutionPlan;
use App\Retrieval\Models\SearchQuery;

interface PlannerStrategy
{
    public function plan(SearchQuery $query): ExecutionPlan;

    public function name(): string;
}
