<?php

namespace App\Livewire\Admin\QueryPlanner;

use App\Contracts\PlannerStrategy;
use Livewire\Component;

class Settings extends Component
{
    public string $selectedStrategy = 'default';

    public function mount(): void
    {
        $this->selectedStrategy = app(PlannerStrategy::class)->name();
    }

    public function render()
    {
        return view('livewire.admin.query-planner.settings', [
            'availableStrategies' => ['default'],
            'strategyName' => $this->selectedStrategy,
        ])->layout('layouts.app', ['header' => 'Query Planner Settings']);
    }
}
