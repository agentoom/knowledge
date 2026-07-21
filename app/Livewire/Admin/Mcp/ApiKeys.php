<?php

namespace App\Livewire\Admin\Mcp;

use App\Models\ApiKey;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Component;

class ApiKeys extends Component
{
    public bool $showCreateModal = false;

    public string $name = '';

    /**
     * @var array<int, string>
     */
    public array $scopes = ['knowledge:read'];

    public ?string $expiresAt = null;

    public string $newKeyPlain = '';

    public function create(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'scopes' => 'required|array|min:1',
            'expiresAt' => 'nullable|date|after:now',
        ]);

        $plain = Str::random(40);

        ApiKey::create([
            'user_id' => auth()->id(),
            'name' => $this->name,
            'key' => Hash::make($plain),
            'scopes' => $this->scopes,
            'expires_at' => $this->expiresAt,
        ]);

        $this->newKeyPlain = $plain;
        $this->reset(['name', 'scopes', 'expiresAt', 'showCreateModal']);
        session()->flash('status', 'API key created.');
    }

    public function revoke(int $id): void
    {
        ApiKey::findOrFail($id)->delete();
        session()->flash('status', 'API key revoked.');
    }

    public function render(): View
    {
        return view('livewire.admin.mcp.api-keys', [
            'keys' => ApiKey::with('user')->latest()->get(),
        ])->layout('layouts.app', ['header' => 'MCP API Keys']);
    }
}
