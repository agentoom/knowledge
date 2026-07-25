<?php

namespace App\Livewire\Admin\Settings;

use App\Settings\Facades\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Component;

class Storage extends Component
{
    public string $knowledgePath = '';

    public string $processingPath = '';

    public bool $isMountedVolume = false;

    public function mount(): void
    {
        $settings = Settings::getMany([
            'storage.knowledge_path',
            'storage.processing_path',
            'storage.is_mounted_volume',
        ], [
            'storage.knowledge_path' => storage_path('app/knowledge'),
            'storage.processing_path' => storage_path('app/processing'),
            'storage.is_mounted_volume' => false,
        ]);

        $this->knowledgePath = $settings['storage.knowledge_path'];
        $this->processingPath = $settings['storage.processing_path'];
        $this->isMountedVolume = (bool) $settings['storage.is_mounted_volume'];
    }

    public function save(): void
    {
        $this->validate([
            'knowledgePath' => ['required', 'string', 'max:1024'],
            'processingPath' => ['required', 'string', 'max:1024'],
        ]);

        Settings::set('storage.knowledge_path', $this->knowledgePath, 'string');
        Settings::set('storage.processing_path', $this->processingPath, 'string');
        Settings::set('storage.is_mounted_volume', $this->isMountedVolume, 'boolean');

        $this->dispatch('notify', message: 'Storage settings saved successfully.');
        $this->dispatch('settings-clean');
    }

    public function restartWorkers(): void
    {
        if (! Gate::allows('viewHorizon')) {
            $this->dispatch('notify', message: 'You are not authorized to restart workers.');

            return;
        }

        // Set the queue restart signal — this is the standard Laravel mechanism
        // that all queue workers (Horizon and vanilla) check between jobs.
        // Workers will gracefully restart after their current job completes.
        Cache::forever('illuminate:queue:restart', now()->getTimestamp());

        $this->dispatch('notify', message: 'Pipeline workers are restarting. This may take a few seconds.');
    }

    public function render(): View
    {
        return view('livewire.admin.settings.storage');
    }
}
