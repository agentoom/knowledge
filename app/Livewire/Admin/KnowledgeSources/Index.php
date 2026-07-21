<?php

namespace App\Livewire\Admin\KnowledgeSources;

use App\Knowledge\Models\KnowledgeSource;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public bool $showCreateModal = false;

    public string $name = '';

    public string $namespace = '';

    public string $providerType = 'filesystem';

    // Create: provider-specific config fields
    public string $configConnectionName = '';

    public bool $configUseDynamicConnection = false;

    public string $configDriver = 'mysql';

    public string $configHost = '';

    public string $configPort = '3306';

    public string $configDatabase = '';

    public string $configUsername = '';

    public string $configPassword = '';

    public string $configTable = '';

    public string $configBasePath = '';

    public string $configUrls = '';

    public bool $showEditModal = false;

    public ?int $editingId = null;

    public string $editName = '';

    public string $editNamespace = '';

    public string $editProviderType = 'filesystem';

    public string $editDescription = '';

    public bool $editIsActive = true;

    // Edit: provider-specific config fields
    public string $editConfigConnectionName = '';

    public bool $editConfigUseDynamicConnection = false;

    public string $editConfigDriver = 'mysql';

    public string $editConfigHost = '';

    public string $editConfigPort = '3306';

    public string $editConfigDatabase = '';

    public string $editConfigUsername = '';

    public string $editConfigPassword = '';

    public string $editConfigTable = '';

    public string $editConfigBasePath = '';

    public string $editConfigUrls = '';

    public function edit(KnowledgeSource $source): void
    {
        $this->editingId = $source->id;
        $this->editName = $source->name;
        $this->editNamespace = $source->namespace;
        $this->editProviderType = $source->provider_type;
        $this->editDescription = $source->description ?? '';
        $this->editIsActive = $source->is_active;

        $config = $source->provider_config ?? [];

        if ($source->provider_type === 'sql') {
            $conn = $config['connection'] ?? '';
            if (is_array($conn)) {
                $this->editConfigUseDynamicConnection = true;
                $this->editConfigDriver = $conn['driver'] ?? 'mysql';
                $this->editConfigHost = $conn['host'] ?? '';
                $this->editConfigPort = (string) ($conn['port'] ?? '3306');
                $this->editConfigDatabase = $conn['database'] ?? '';
                $this->editConfigUsername = $conn['username'] ?? '';
                $this->editConfigPassword = $conn['password'] ?? '';
            } else {
                $this->editConfigUseDynamicConnection = false;
                $this->editConfigConnectionName = $conn;
            }
            $this->editConfigTable = $config['table'] ?? '';
        } elseif (in_array($source->provider_type, ['filesystem', 'yaml', 'json'], true)) {
            $this->editConfigBasePath = $config['basePath'] ?? '';
        } elseif ($source->provider_type === 'web') {
            $this->editConfigUrls = implode("\n", $config['urls'] ?? []);
        }

        $this->showEditModal = true;
    }

    public function update(): void
    {
        $this->validate([
            'editName' => 'required|string|max:255',
            'editNamespace' => 'required|string|max:100',
            'editProviderType' => 'required|string|in:filesystem,sql,yaml,json,web,vector_store',
            ...$this->editConfigRules(),
        ]);

        $source = KnowledgeSource::findOrFail($this->editingId);
        $source->update([
            'name' => $this->editName,
            'slug' => str($this->editName)->slug(),
            'namespace' => $this->editNamespace,
            'provider_type' => $this->editProviderType,
            'description' => $this->editDescription,
            'is_active' => $this->editIsActive,
            'provider_config' => $this->buildEditProviderConfig(),
        ]);

        $this->reset([
            'editingId', 'editName', 'editNamespace', 'editProviderType',
            'editDescription', 'editIsActive', 'showEditModal',
            'editConfigConnectionName', 'editConfigUseDynamicConnection',
            'editConfigDriver', 'editConfigHost', 'editConfigPort',
            'editConfigDatabase', 'editConfigUsername', 'editConfigPassword',
            'editConfigTable', 'editConfigBasePath', 'editConfigUrls',
        ]);
        session()->flash('status', 'Knowledge source updated.');
    }

    public function create(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'namespace' => 'required|string|max:100',
            'providerType' => 'required|string|in:filesystem,sql,yaml,json,web,vector_store',
            ...$this->configRules(),
        ]);

        KnowledgeSource::create([
            'name' => $this->name,
            'slug' => str($this->name)->slug(),
            'namespace' => $this->namespace,
            'provider_type' => $this->providerType,
            'provider_config' => $this->buildProviderConfig(),
        ]);

        $this->reset([
            'name', 'namespace', 'providerType', 'showCreateModal',
            'configConnectionName', 'configUseDynamicConnection',
            'configDriver', 'configHost', 'configPort',
            'configDatabase', 'configUsername', 'configPassword',
            'configTable', 'configBasePath', 'configUrls',
        ]);
        session()->flash('status', 'Knowledge source created.');
    }

    public function delete(int $id): void
    {
        KnowledgeSource::findOrFail($id)->delete();
        session()->flash('status', 'Knowledge source deleted.');
    }

    public function toggleActive(int $id): void
    {
        $source = KnowledgeSource::findOrFail($id);
        $source->update(['is_active' => ! $source->is_active]);
        session()->flash('status', 'Knowledge source status updated.');
    }

    /**
     * @return array<string, array<string, string>|string>
     */
    private function configRules(): array
    {
        return match ($this->providerType) {
            'sql' => $this->sqlConfigRules(),
            'filesystem', 'yaml', 'json' => $this->pathConfigRules(),
            'web' => $this->webConfigRules(),
            default => [],
        };
    }

    /**
     * @return array<string, array<string, string>|string>
     */
    private function editConfigRules(): array
    {
        return match ($this->editProviderType) {
            'sql' => $this->editSqlConfigRules(),
            'filesystem', 'yaml', 'json' => $this->editPathConfigRules(),
            'web' => $this->editWebConfigRules(),
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    private function sqlConfigRules(): array
    {
        if ($this->configUseDynamicConnection) {
            return [
                'configTable' => 'required|string|max:255',
                'configDriver' => 'required|string|in:mysql,pgsql,sqlite,sqlsrv',
                'configHost' => 'required|string|max:255',
                'configPort' => 'nullable|string|max:10',
                'configDatabase' => 'required|string|max:255',
                'configUsername' => 'nullable|string|max:255',
                'configPassword' => 'nullable|string|max:255',
            ];
        }

        return [
            'configConnectionName' => 'required|string|max:255',
            'configTable' => 'required|string|max:255',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function editSqlConfigRules(): array
    {
        if ($this->editConfigUseDynamicConnection) {
            return [
                'editConfigTable' => 'required|string|max:255',
                'editConfigDriver' => 'required|string|in:mysql,pgsql,sqlite,sqlsrv',
                'editConfigHost' => 'required|string|max:255',
                'editConfigPort' => 'nullable|string|max:10',
                'editConfigDatabase' => 'required|string|max:255',
                'editConfigUsername' => 'nullable|string|max:255',
                'editConfigPassword' => 'nullable|string|max:255',
            ];
        }

        return [
            'editConfigConnectionName' => 'required|string|max:255',
            'editConfigTable' => 'required|string|max:255',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function pathConfigRules(): array
    {
        return ['configBasePath' => 'required|string|max:512'];
    }

    /**
     * @return array<string, string>
     */
    private function editPathConfigRules(): array
    {
        return ['editConfigBasePath' => 'required|string|max:512'];
    }

    /**
     * @return array<string, string>
     */
    private function webConfigRules(): array
    {
        return ['configUrls' => 'required|string'];
    }

    /**
     * @return array<string, string>
     */
    private function editWebConfigRules(): array
    {
        return ['editConfigUrls' => 'required|string'];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProviderConfig(): array
    {
        return match ($this->providerType) {
            'sql' => $this->buildSqlConfig(),
            'filesystem', 'yaml', 'json' => ['basePath' => $this->configBasePath],
            'web' => ['urls' => array_filter(explode("\n", str_replace("\r", '', $this->configUrls)))],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEditProviderConfig(): array
    {
        return match ($this->editProviderType) {
            'sql' => $this->buildEditSqlConfig(),
            'filesystem', 'yaml', 'json' => ['basePath' => $this->editConfigBasePath],
            'web' => ['urls' => array_filter(explode("\n", str_replace("\r", '', $this->editConfigUrls)))],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSqlConfig(): array
    {
        $config = ['table' => $this->configTable];

        if ($this->configUseDynamicConnection) {
            $config['connection'] = [
                'driver' => $this->configDriver,
                'host' => $this->configHost,
                'port' => (int) $this->configPort ?: 3306,
                'database' => $this->configDatabase,
                'username' => $this->configUsername,
                'password' => $this->configPassword,
            ];
        } else {
            $config['connection'] = $this->configConnectionName;
        }

        return $config;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEditSqlConfig(): array
    {
        $config = ['table' => $this->editConfigTable];

        if ($this->editConfigUseDynamicConnection) {
            $config['connection'] = [
                'driver' => $this->editConfigDriver,
                'host' => $this->editConfigHost,
                'port' => (int) $this->editConfigPort ?: 3306,
                'database' => $this->editConfigDatabase,
                'username' => $this->editConfigUsername,
                'password' => $this->editConfigPassword,
            ];
        } else {
            $config['connection'] = $this->editConfigConnectionName;
        }

        return $config;
    }

    public function render(): View
    {
        return view('livewire.admin.knowledge-sources.index', [
            'sources' => KnowledgeSource::orderBy('name')->paginate(15),
        ])->layout('layouts.app', ['header' => 'Knowledge Sources']);
    }
}
