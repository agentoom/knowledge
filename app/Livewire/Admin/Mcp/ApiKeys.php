<?php

namespace App\Livewire\Admin\Mcp;

use App\Knowledge\Services\MetadataRegistryService;
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

    /**
     * @var array<int, string>
     */
    public array $knowledgeNamespaces = [];

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
            'knowledge_namespaces' => $this->knowledgeNamespaces,
            'expires_at' => $this->expiresAt,
        ]);

        $this->newKeyPlain = $plain;
        $this->reset(['name', 'scopes', 'knowledgeNamespaces', 'expiresAt', 'showCreateModal']);
        session()->flash('status', 'API key created.');
    }

    public function selectAllNamespaces(): void
    {
        $this->knowledgeNamespaces = $this->availableNamespaces();
    }

    public function deselectAllNamespaces(): void
    {
        $this->knowledgeNamespaces = [];
    }

    /**
     * @return array<int, string>
     */
    public function availableNamespaces(): array
    {
        $registry = app(MetadataRegistryService::class)->get();

        return $registry['namespaces'] ?? [];
    }

    public function render(): View
    {
        return view('livewire.admin.mcp.api-keys', [
            'keys' => ApiKey::with('user')->latest()->get(),
            'availableNamespaces' => $this->availableNamespaces(),
        ])->layout('layouts.app', ['header' => 'MCP API Keys']);
    }

    public function revoke(int $id): void
    {
        ApiKey::findOrFail($id)->delete();
        session()->flash('status', 'API key revoked.');
    }
}
