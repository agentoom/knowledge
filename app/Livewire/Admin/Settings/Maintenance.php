<?php

namespace App\Livewire\Admin\Settings;

use App\Settings\Facades\Settings;
use Illuminate\View\View;
use Livewire\Component;

class Maintenance extends Component
{
    public int $federationSyncInterval = 15;

    public bool $logPruningEnabled = true;

    public int $logPruningAgeDays = 30;

    public bool $retrievalLogPruningEnabled = true;

    public int $retrievalLogPruningAgeDays = 30;

    public function mount(): void
    {
        $settings = Settings::getMany([
            'maintenance.federation_sync_interval',
            'maintenance.log_pruning_enabled',
            'maintenance.log_pruning_age_days',
            'maintenance.retrieval_log_pruning_enabled',
            'maintenance.retrieval_log_pruning_age_days',
        ], [
            'maintenance.federation_sync_interval' => 15,
            'maintenance.log_pruning_enabled' => true,
            'maintenance.log_pruning_age_days' => 30,
            'maintenance.retrieval_log_pruning_enabled' => true,
            'maintenance.retrieval_log_pruning_age_days' => 30,
        ]);

        $this->federationSyncInterval = (int) $settings['maintenance.federation_sync_interval'];
        $this->logPruningEnabled = (bool) $settings['maintenance.log_pruning_enabled'];
        $this->logPruningAgeDays = (int) $settings['maintenance.log_pruning_age_days'];
        $this->retrievalLogPruningEnabled = (bool) $settings['maintenance.retrieval_log_pruning_enabled'];
        $this->retrievalLogPruningAgeDays = (int) $settings['maintenance.retrieval_log_pruning_age_days'];
    }

    public function save(): void
    {
        $this->validate([
            'federationSyncInterval' => ['required', 'integer', 'min:1', 'max:1440'],
            'logPruningAgeDays' => ['required', 'integer', 'min:1', 'max:365'],
            'retrievalLogPruningAgeDays' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        Settings::set('maintenance.federation_sync_interval', $this->federationSyncInterval, 'integer');
        Settings::set('maintenance.log_pruning_enabled', $this->logPruningEnabled, 'boolean');
        Settings::set('maintenance.log_pruning_age_days', $this->logPruningAgeDays, 'integer');
        Settings::set('maintenance.retrieval_log_pruning_enabled', $this->retrievalLogPruningEnabled, 'boolean');
        Settings::set('maintenance.retrieval_log_pruning_age_days', $this->retrievalLogPruningAgeDays, 'integer');

        $this->dispatch('notify', message: 'Maintenance settings saved successfully.');
        $this->dispatch('settings-clean');
    }

    public function render(): View
    {
        return view('livewire.admin.settings.maintenance');
    }
}
