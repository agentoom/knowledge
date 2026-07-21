<?php

namespace App\Livewire\Admin\KnowledgeSources;

use App\DocumentPipeline\Services\PipelineOrchestrator;
use App\Knowledge\Models\KnowledgeSource;
use Illuminate\View\View;
use Livewire\Component;

class Show extends Component
{
    public ?string $name = null;

    public ?string $namespace = null;

    public ?string $providerType = null;

    public ?string $description = null;

    public bool $isActive = false;

    public ?int $priority = null;

    public ?string $createdAt = null;

    public ?string $providerName = null;

    public ?string $providerStatus = null;

    public int $documentCount = 0;

    public int $activeDocumentCount = 0;

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $documents = [];

    /**
     * @var array<string, mixed>
     */
    public array $providerConfig = [];

    public bool $isEditingConfig = false;

    public string $configJson = '';

    public int $sourceId;

    // Structured config fields
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

    public bool $useFormEditor = true;

    public function mount(int $source): void
    {
        $this->sourceId = $source;
        $source = KnowledgeSource::with(['providers', 'documents'])->findOrFail($source);

        $this->name = $source->name;
        $this->namespace = $source->namespace;
        $this->providerType = $source->provider_type;
        $this->description = $source->description;
        $this->isActive = $source->is_active;
        $this->priority = $source->priority;
        $this->createdAt = $source->created_at?->toDateTimeString();
        $this->providerConfig = $source->provider_config ?? [];
        $this->configJson = (string) json_encode($this->providerConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $this->loadConfigIntoFormFields($source);

        $provider = $source->providers->first();
        $this->providerName = $provider?->name;
        $this->providerStatus = $provider?->status;

        $this->documentCount = $source->documents->count();
        $this->activeDocumentCount = $source->documents->where('status', 'indexed')->count();

        $this->documents = $source->documents()
            ->latest()
            ->take(20)
            ->get()
            ->map(fn ($doc) => [
                'id' => $doc->id,
                'filename' => $doc->filename,
                'status' => $doc->status,
                'size_bytes' => $doc->size_bytes,
                'created_at' => $doc->created_at?->diffForHumans(),
            ])
            ->all();
    }

    private function loadConfigIntoFormFields(KnowledgeSource $source): void
    {
        $config = $source->provider_config ?? [];

        if ($source->provider_type === 'sql') {
            $conn = $config['connection'] ?? '';
            if (is_array($conn)) {
                $this->configUseDynamicConnection = true;
                $this->configDriver = $conn['driver'] ?? 'mysql';
                $this->configHost = $conn['host'] ?? '';
                $this->configPort = (string) ($conn['port'] ?? '3306');
                $this->configDatabase = $conn['database'] ?? '';
                $this->configUsername = $conn['username'] ?? '';
                $this->configPassword = $conn['password'] ?? '';
            } else {
                $this->configUseDynamicConnection = false;
                $this->configConnectionName = $conn;
            }
            $this->configTable = $config['table'] ?? '';
        } elseif (in_array($source->provider_type, ['filesystem', 'yaml', 'json'], true)) {
            $this->configBasePath = $config['basePath'] ?? '';
        } elseif ($source->provider_type === 'web') {
            $this->configUrls = implode("\n", $config['urls'] ?? []);
        }
    }

    public function saveConfig(): void
    {
        if ($this->useFormEditor) {
            $this->saveConfigFromForm();

            return;
        }

        $this->validate([
            'configJson' => 'required|json',
        ]);

        $source = KnowledgeSource::findOrFail($this->sourceId);
        $source->update([
            'provider_config' => json_decode($this->configJson, true),
        ]);

        $this->providerConfig = $source->provider_config;
        $this->isEditingConfig = false;

        session()->flash('status', 'Configuration updated successfully.');
    }

    public function saveConfigFromForm(): void
    {
        $rules = match ($this->providerType) {
            'sql' => $this->sqlFormRules(),
            'filesystem', 'yaml', 'json' => ['configBasePath' => 'required|string|max:512'],
            'web' => ['configUrls' => 'required|string'],
            default => [],
        };

        $this->validate($rules);

        $config = match ($this->providerType) {
            'sql' => $this->buildSqlFormConfig(),
            'filesystem', 'yaml', 'json' => ['basePath' => $this->configBasePath],
            'web' => ['urls' => array_filter(explode("\n", str_replace("\r", '', $this->configUrls)))],
            default => [],
        };

        $source = KnowledgeSource::findOrFail($this->sourceId);
        $source->update(['provider_config' => $config]);

        $this->providerConfig = $source->provider_config;
        $this->configJson = (string) json_encode($this->providerConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->isEditingConfig = false;

        session()->flash('status', 'Configuration updated successfully.');
    }

    /**
     * @return array<string, string>
     */
    private function sqlFormRules(): array
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
     * @return array<string, mixed>
     */
    private function buildSqlFormConfig(): array
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

    public function startSync(): void
    {
        $source = KnowledgeSource::findOrFail($this->sourceId);

        app(PipelineOrchestrator::class)->run($source);

        session()->flash('status', 'Discovery and indexing pipeline started.');
    }

    public function render(): View
    {
        return view('livewire.admin.knowledge-sources.show')
            ->layout('layouts.app', ['header' => 'Knowledge Source Detail']);
    }
}
