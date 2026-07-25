<?php

namespace App\Livewire\Admin\Providers;

use App\Knowledge\Models\Provider;
use Carbon\CarbonInterface;
use Illuminate\View\View;
use Livewire\Component;

class Configure extends Component
{
    public ?int $providerId = null;

    public string $name = '';

    public string $class = '';

    public string $type = '';

    public string $status = '';

    public string $metadata = '';

    public ?string $sourceName = null;

    public ?string $lastSyncedAt = null;

    public ?string $errorMessage = null;

    public function mount(int $provider): void
    {
        $provider = Provider::with('knowledgeSource')->findOrFail($provider);

        $this->providerId = $provider->id;
        $this->name = $provider->name;
        $this->class = $provider->class;
        $this->type = $provider->type;
        $this->status = $provider->status;
        $this->metadata = (string) json_encode($provider->metadata, JSON_PRETTY_PRINT);
        $this->sourceName = $provider->knowledgeSource?->name;
        $this->lastSyncedAt = $provider->last_synced_at instanceof CarbonInterface
            ? $provider->last_synced_at->toDateTimeString()
            : null;
        $this->errorMessage = $provider->error_message;
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:100',
            'status' => 'required|string|in:active,inactive,error,syncing',
            'metadata' => 'nullable|json',
        ]);

        $provider = Provider::findOrFail($this->providerId);

        $provider->update([
            'name' => $this->name,
            'type' => $this->type,
            'status' => $this->status,
            'metadata' => json_decode($this->metadata, true),
        ]);

        session()->flash('status', 'Provider configuration saved successfully.');
    }

    public function render(): View
    {
        return view('livewire.admin.providers.configure')
            ->layout('layouts.app', ['header' => 'Configure Provider']);
    }
}
