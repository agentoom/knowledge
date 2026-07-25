<?php

namespace App\Livewire\Admin\Settings;

use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{
    #[Url(as: 'tab')]
    public string $tab = 'general';

    public function selectTab(string $tab): void
    {
        $this->tab = $tab;
    }

    public function render(): View
    {
        return view('livewire.admin.settings.index')
            ->layout('layouts.app', ['header' => 'Settings']);
    }
}
