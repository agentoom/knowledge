<?php

namespace App\Livewire\Admin\KnowledgeSources;

use App\Knowledge\Enums\ProviderType;
use App\Knowledge\Models\KnowledgeSource;
use App\Knowledge\Services\KnowledgeSourceTemplateRegistry;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Multi-step creation wizard for knowledge sources.
 *
 * Step 1: Configure (name, namespace, provider type, description)
 * Step 2: Review directory path & create the source
 * Step 3: File manager (upload files into the created source)
 */
class Create extends Component
{
    public int $step = 1;

    // Step 1 fields
    public string $name = '';

    public string $namespace = '';

    public string $providerType = 'filesystem';

    public string $description = '';

    public bool $isActive = true;

    public bool $showSourceTypesHelp = false;

    // Step 2 fields (computed)
    public ?int $createdSourceId = null;

    public string $directoryPath = '';

    public bool $directoryExists = false;

    /** @var array<int, string> */
    public array $allowedExtensions = [];

    // SQL-specific
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

    public string $selectedTemplate = '';

    public function nextStep(): void
    {
        if ($this->step === 1) {
            $this->validateStep1();
            $this->computeDirectoryPath();
            $this->step = 2;
        } elseif ($this->step === 2) {
            $this->createSource();
            $this->step = 3;
        }
    }

    public function previousStep(): void
    {
        if ($this->step > 1) {
            $this->step--;
        }
    }

    public function goToStep(int $step): void
    {
        if ($step >= 1 && $step <= $this->step) {
            $this->step = $step;
        }
    }

    public function finish(): void
    {
        session()->flash('status', 'Knowledge source "'.$this->name.'" created successfully.');

        $this->redirect(route('admin.knowledge-sources.show', $this->createdSourceId), navigate: true);
    }

    protected function validateStep1(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'namespace' => 'required|string|max:100|regex:/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/i',
            'providerType' => 'required|string|in:filesystem,sql,yaml,json,markdown,web',
            'description' => 'nullable|string|max:1000',
        ]);

        // Template defaults are reusable, so a slug collision must surface as
        // a field error instead of a database unique-key exception on create.
        $slug = str($this->name)->slug()->toString();

        if (KnowledgeSource::where('slug', $slug)->exists()) {
            throw ValidationException::withMessages([
                'name' => "A knowledge source with the slug \"{$slug}\" already exists. Choose a different name.",
            ]);
        }
    }

    /**
     * Load a config template into the wizard fields.
     *
     * Stale fields from a previously selected provider type are cleared, and
     * secrets are left blank for the user to review before creation.
     */
    public function applyTemplate(string $key): void
    {
        try {
            $template = app(KnowledgeSourceTemplateRegistry::class)->get($key);
        } catch (InvalidArgumentException $e) {
            $this->addError('selectedTemplate', $e->getMessage());

            return;
        }

        $this->resetProviderConfigFields();
        $this->resetValidation('selectedTemplate');

        $this->selectedTemplate = $key;
        $this->name = $template['default_name'];
        $this->namespace = $template['namespace'];
        $this->description = $template['description'];
        $this->providerType = $template['provider_type'];

        $this->applyTemplateConfig($template['provider_config']);
    }

    /**
     * Reset every provider-specific field so a previously selected type's
     * values never leak into a newly applied template.
     */
    private function resetProviderConfigFields(): void
    {
        $this->configConnectionName = '';
        $this->configUseDynamicConnection = false;
        $this->configDriver = 'mysql';
        $this->configPort = '3306';
        $this->configHost = '';
        $this->configDatabase = '';
        $this->configUsername = '';
        $this->configPassword = '';
        $this->configTable = '';
        $this->configBasePath = '';
        $this->configUrls = '';
    }

    /**
     * Map only the existing SQL/web/filesystem config fields from the preset.
     *
     * @param  array<string, mixed>  $config
     */
    private function applyTemplateConfig(array $config): void
    {
        if ($this->providerType === 'web') {
            $urls = $config['urls'] ?? [];

            $this->configUrls = is_array($urls) ? implode("\n", $urls) : '';

            return;
        }

        if ($this->providerType === 'sql') {
            $connection = $config['connection'] ?? '';

            if (is_string($connection)) {
                $this->configConnectionName = $connection;
            }

            $this->configTable = (string) ($config['table'] ?? '');
        }
    }

    protected function computeDirectoryPath(): void
    {
        $type = ProviderType::tryFrom($this->providerType);

        if (! $type || ! $type->isFilesystemBacked()) {
            return;
        }

        $this->directoryPath = $type->canonicalPath($this->namespace);
        $this->directoryExists = $this->directoryPath !== '' && is_dir($this->directoryPath);
        $this->allowedExtensions = $type->allowedExtensions();
    }

    protected function createSource(): void
    {
        $type = $this->providerType;

        $config = match ($type) {
            'sql' => $this->buildSqlConfig(),
            'web' => ['urls' => array_filter(explode("\n", str_replace("\r", '', $this->configUrls)))],
            default => [],
        };

        $source = KnowledgeSource::create([
            'name' => $this->name,
            'slug' => str($this->name)->slug(),
            'namespace' => $this->namespace,
            'provider_type' => $type,
            'description' => $this->description,
            'is_active' => $this->isActive,
            'provider_config' => $config,
        ]);

        // Ensure directory is created for filesystem-backed sources. The
        // sync pipeline may already have created it during create(), so the
        // check is done against the filesystem rather than the stale flag.
        if ($this->directoryPath !== '' && ! is_dir($this->directoryPath)) {
            mkdir($this->directoryPath, 0755, true);
            $this->directoryExists = true;
        }

        $this->createdSourceId = $source->id;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildSqlConfig(): array
    {
        $config = ['table' => $this->configTable];

        if ($this->configUseDynamicConnection) {
            $config['connection'] = [
                'driver' => $this->configDriver,
                'host' => $this->configHost,
                'port' => (int) $this->configPort ?: 3306,
                'database' => $this->configDatabase,
                'username' => $this->configUsername,
                'password' => Crypt::encryptString($this->configPassword),
            ];
        } else {
            $config['connection'] = $this->configConnectionName;
        }

        return $config;
    }

    public function getProviderLabelProperty(): string
    {
        $type = ProviderType::tryFrom($this->providerType);

        return $type?->label() ?? ucfirst($this->providerType);
    }

    public function getProviderExtensionsLabelProperty(): string
    {
        return ProviderType::tryFrom($this->providerType)?->acceptedFormatsLabel() ?? '';
    }

    public function render(): View
    {
        return view('livewire.admin.knowledge-sources.create', [
            'templates' => app(KnowledgeSourceTemplateRegistry::class)->all(),
        ])->layout('layouts.app', ['header' => 'Create Knowledge Source']);
    }
}
