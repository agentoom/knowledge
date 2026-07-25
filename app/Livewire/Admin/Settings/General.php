<?php

namespace App\Livewire\Admin\Settings;

use App\Settings\Facades\Settings;
use Illuminate\View\View;
use Livewire\Component;

class General extends Component
{
    public string $appName = '';

    public string $appDescription = '';

    public function mount(): void
    {
        $settings = Settings::getMany([
            'knowledge.app_name',
            'knowledge.app_description',
        ], [
            'knowledge.app_name' => config('app.name'),
            'knowledge.app_description' => '',
        ]);

        $this->appName = $settings['knowledge.app_name'];
        $this->appDescription = $settings['knowledge.app_description'];
    }

    public function save(): void
    {
        $this->validate([
            'appName' => ['required', 'string', 'max:255'],
        ]);

        Settings::set('knowledge.app_name', $this->appName, 'string');
        Settings::set('knowledge.app_description', $this->appDescription, 'string');

        $this->dispatch('notify', message: 'General settings saved successfully.');
        $this->dispatch('settings-clean');
    }

    public function render(): View
    {
        return view('livewire.admin.settings.general');
    }
}
