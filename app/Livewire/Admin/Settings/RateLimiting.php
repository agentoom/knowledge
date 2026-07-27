<?php

namespace App\Livewire\Admin\Settings;

use App\Settings\Facades\Settings;
use Illuminate\View\View;
use Livewire\Component;

class RateLimiting extends Component
{
    public bool $rateLimitingEnabled = true;

    public int $rateLimitPerMinute = 60;

    public function mount(): void
    {
        $settings = Settings::getMany([
            'mcp.rate_limiting_enabled',
            'mcp.rate_limit_per_minute',
        ], [
            'mcp.rate_limiting_enabled' => true,
            'mcp.rate_limit_per_minute' => 60,
        ]);

        $this->rateLimitingEnabled = (bool) $settings['mcp.rate_limiting_enabled'];
        $this->rateLimitPerMinute = (int) $settings['mcp.rate_limit_per_minute'];
    }

    public function save(): void
    {
        $this->validate([
            'rateLimitPerMinute' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        Settings::set('mcp.rate_limiting_enabled', $this->rateLimitingEnabled, 'boolean');
        Settings::set('mcp.rate_limit_per_minute', $this->rateLimitPerMinute, 'integer');

        $this->dispatch('notify', message: 'Rate limiting settings saved successfully.');
        $this->dispatch('settings-clean');
    }

    public function render(): View
    {
        return view('livewire.admin.settings.rate-limiting');
    }
}
