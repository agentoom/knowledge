<?php

namespace App\Livewire\Admin\Settings;

use App\Settings\Facades\Settings;
use Livewire\Component;

class General extends Component
{
    public string $appName = '';

    public string $appDescription = '';

    public int $defaultMaxResults = 10;

    public string $defaultPlannerStrategy = 'default';

    public string $defaultChunkingStrategy = 'fixed_size';

    public int $chunkSize = 1000;

    public int $chunkOverlap = 200;

    public array $availablePlannerStrategies = [];

    public array $availableChunkingStrategies = [];

    public function mount(): void
    {
        $this->appName = Settings::get('knowledge.app_name', config('app.name'));
        $this->appDescription = Settings::get('knowledge.app_description', '');
        $this->defaultMaxResults = Settings::get('knowledge.default_max_results', 10);
        $this->defaultPlannerStrategy = Settings::get('knowledge.default_planner_strategy', 'default');
        $this->defaultChunkingStrategy = Settings::get('knowledge.default_chunking_strategy', 'fixed_size');
        $this->chunkSize = Settings::get('knowledge.chunk_size', 1000);
        $this->chunkOverlap = Settings::get('knowledge.chunk_overlap', 200);

        $this->availablePlannerStrategies = [
            'default' => 'Default (Rule-based)',
            'namespace' => 'Namespace Scoped',
            'hybrid' => 'Hybrid (Parallel)',
        ];

        $this->availableChunkingStrategies = [
            'fixed_size' => 'Fixed Size',
            'markdown' => 'Markdown',
            'recursive' => 'Recursive',
        ];
    }

    public function save(): void
    {
        $this->validate([
            'appName' => ['required', 'string', 'max:255'],
            'defaultMaxResults' => ['required', 'integer', 'min:1', 'max:100'],
            'chunkSize' => ['required', 'integer', 'min:100', 'max:10000'],
            'chunkOverlap' => ['required', 'integer', 'min:0', 'max:1000'],
        ]);

        Settings::set('knowledge.app_name', $this->appName, 'string');
        Settings::set('knowledge.app_description', $this->appDescription, 'string');
        Settings::set('knowledge.default_max_results', $this->defaultMaxResults, 'integer');
        Settings::set('knowledge.default_planner_strategy', $this->defaultPlannerStrategy, 'string');
        Settings::set('knowledge.default_chunking_strategy', $this->defaultChunkingStrategy, 'string');
        Settings::set('knowledge.chunk_size', $this->chunkSize, 'integer');
        Settings::set('knowledge.chunk_overlap', $this->chunkOverlap, 'integer');

        $this->dispatch('notify', message: 'General settings saved successfully.');
    }

    public function render()
    {
        return view('livewire.admin.settings.general');
    }
}
