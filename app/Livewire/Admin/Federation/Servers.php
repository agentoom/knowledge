<?php

namespace App\Livewire\Admin\Federation;

use App\Federation\FederationManager;
use App\Models\FederatedServer;
use Illuminate\View\View;
use Livewire\Component;

class Servers extends Component
{
    public bool $showCreateModal = false;

    public bool $showEditModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $endpointUrl = '';

    public string $authToken = '';

    public int $priority = 0;

    public bool $isActive = true;

    public string $editName = '';

    public string $editEndpointUrl = '';

    public string $editAuthToken = '';

    public int $editPriority = 0;

    public bool $editIsActive = true;

    public function create(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'endpointUrl' => 'required|url|max:512',
            'authToken' => 'required|string|min:10',
            'priority' => 'integer|min:0',
        ]);

        FederatedServer::create([
            'name' => $this->name,
            'endpoint_url' => $this->endpointUrl,
            'auth_token' => encrypt($this->authToken),
            'priority' => $this->priority,
            'is_active' => $this->isActive,
        ]);

        $this->reset(['name', 'endpointUrl', 'authToken', 'priority', 'isActive', 'showCreateModal']);
        session()->flash('status', 'Federated server added.');
    }

    public function edit(FederatedServer $server): void
    {
        $this->editingId = $server->id;
        $this->editName = $server->name;
        $this->editEndpointUrl = $server->endpoint_url;
        $this->editAuthToken = '';
        $this->editPriority = $server->priority;
        $this->editIsActive = $server->is_active;
        $this->showEditModal = true;
    }

    public function update(): void
    {
        $rules = [
            'editName' => 'required|string|max:255',
            'editEndpointUrl' => 'required|url|max:512',
            'editPriority' => 'integer|min:0',
        ];

        if ($this->editAuthToken !== '' && $this->editAuthToken !== '0') {
            $rules['editAuthToken'] = 'string|min:10';
        }

        $this->validate($rules);

        $server = FederatedServer::findOrFail($this->editingId);

        $data = [
            'name' => $this->editName,
            'endpoint_url' => $this->editEndpointUrl,
            'priority' => $this->editPriority,
            'is_active' => $this->editIsActive,
        ];

        if ($this->editAuthToken !== '' && $this->editAuthToken !== '0') {
            $data['auth_token'] = encrypt($this->editAuthToken);
        }

        $server->update($data);

        $this->reset([
            'editingId', 'editName', 'editEndpointUrl', 'editAuthToken',
            'editPriority', 'editIsActive', 'showEditModal',
        ]);
        session()->flash('status', 'Federated server updated.');
    }

    public function delete(int $id): void
    {
        FederatedServer::findOrFail($id)->delete();
        session()->flash('status', 'Federated server removed.');
    }

    public function toggleActive(int $id): void
    {
        $server = FederatedServer::findOrFail($id);
        $server->update(['is_active' => ! $server->is_active]);
        session()->flash('status', 'Server status updated.');
    }

    public function sync(int $id, FederationManager $manager): void
    {
        $server = FederatedServer::findOrFail($id);
        $manager->syncCapabilities($server);
        session()->flash('status', 'Capabilities synced from remote server.');
    }

    public function render(): View
    {
        return view('livewire.admin.federation.servers', [
            'servers' => FederatedServer::orderBy('priority', 'desc')->get(),
        ])->layout('layouts.app', ['header' => 'Federation Servers']);
    }
}
