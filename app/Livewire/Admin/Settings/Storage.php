<?php

namespace App\Livewire\Admin\Settings;

use App\Settings\Facades\Settings;
use Livewire\Component;

class Storage extends Component
{
    public string $knowledgePath = '';

    public string $processingPath = '';

    public bool $isMountedVolume = false;

    public function mount(): void
    {
        $this->knowledgePath = Settings::get('storage.knowledge_path', storage_path('app/knowledge'));
        $this->processingPath = Settings::get('storage.processing_path', storage_path('app/processing'));
        $this->isMountedVolume = Settings::get('storage.is_mounted_volume', false);
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
    }

    public function render()
    {
        return view('livewire.admin.settings.storage');
    }
}
