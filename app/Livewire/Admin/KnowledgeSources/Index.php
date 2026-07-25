<?php

namespace App\Livewire\Admin\KnowledgeSources;

use App\DocumentPipeline\Services\PipelineOrchestrator;
use App\Jobs\DocumentPipeline\SyncKnowledgeSource;
use App\Knowledge\Enums\ProviderType;
use App\Knowledge\Models\Document;
use App\Knowledge\Models\KnowledgeSource;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Index extends Component
{
    use WithFileUploads;
    use WithPagination;

    public bool $showEditModal = false;

    public bool $showSourceTypesHelp = false;

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

    /**
     * @var array<int, UploadedFile>
     */
    public array $editUploadedFiles = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $existingFiles = [];

    public ?string $editStatusMessage = null;

    public ?string $editErrorMessage = null;

    /** @var array<int, string> */
    private array $filesystemTypes = ['filesystem', 'yaml', 'json', 'markdown'];

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
                $this->editConfigPassword = '';
            } else {
                $this->editConfigUseDynamicConnection = false;
                $this->editConfigConnectionName = is_string($conn) ? $conn : '';
            }
            $this->editConfigTable = $config['table'] ?? '';
        } elseif (in_array($source->provider_type, $this->filesystemTypes, true)) {
            $this->editConfigBasePath = $config['basePath'] ?? '';
        } elseif ($source->provider_type === 'web') {
            $this->editConfigUrls = implode("\n", $config['urls'] ?? []);
        }

        if (in_array($source->provider_type, $this->filesystemTypes, true)) {
            $this->existingFiles = $source->documents()
                ->latest()
                ->limit(100)
                ->get()
                ->map(fn (Document $doc) => [
                    'id' => $doc->id,
                    'filename' => $doc->filename,
                    'size_bytes' => $doc->size_bytes,
                    'mime_type' => $doc->mime_type,
                    'created_at' => $doc->created_at?->diffForHumans(),
                ])
                ->all();
        }

        $this->showEditModal = true;
    }

    public function update(): void
    {
        $validTypes = implode(',', [...$this->filesystemTypes, 'sql', 'web']);

        $this->validate([
            'editName' => 'required|string|max:255',
            'editNamespace' => 'required|string|max:100',
            'editProviderType' => "required|string|in:{$validTypes}",
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

        if (in_array($this->editProviderType, $this->filesystemTypes, true) && ! empty($this->editUploadedFiles)) {
            try {
                $this->storeUploadedFiles($source, $this->editUploadedFiles);
                $this->editStatusMessage = 'Files uploaded successfully.';
            } catch (\Throwable $e) {
                $this->editErrorMessage = 'Failed to upload files: '.$e->getMessage();
            }
        }

        $this->reset([
            'editingId', 'editName', 'editNamespace', 'editProviderType',
            'editDescription', 'editIsActive', 'showEditModal',
            'editConfigConnectionName', 'editConfigUseDynamicConnection',
            'editConfigDriver', 'editConfigHost', 'editConfigPort',
            'editConfigDatabase', 'editConfigUsername', 'editConfigPassword',
            'editConfigTable', 'editConfigBasePath', 'editConfigUrls',
            'editUploadedFiles', 'existingFiles',
        ]);
        session()->flash('status', 'Knowledge source updated.');
    }

    public function delete(int $id): void
    {
        KnowledgeSource::findOrFail($id)->delete();
        session()->flash('status', 'Knowledge source deleted.');
    }

    public function removeFile(int $documentId): void
    {
        $document = Document::where('id', $documentId)
            ->where('knowledge_source_id', $this->editingId)
            ->firstOrFail();

        $basePath = config('knowledge.base_path');

        if ($document->path) {
            $resolvedPath = realpath($document->path) ?: $document->path;

            if (! str_starts_with($resolvedPath, (string) $basePath) || str_contains($document->path, '..')) {
                abort(403, 'Invalid document path.');
            }

            if (file_exists($document->path)) {
                unlink($document->path);
            }
        }

        $document->delete();

        $this->existingFiles = array_values(
            array_filter($this->existingFiles, fn ($file) => $file['id'] !== $documentId)
        );

        session()->flash('status', 'File removed successfully.');
    }

    public function toggleActive(int $id): void
    {
        $source = KnowledgeSource::findOrFail($id);
        $source->update(['is_active' => ! $source->is_active]);
        session()->flash('status', 'Knowledge source status updated.');
    }

    public function clearEditMessages(): void
    {
        $this->editStatusMessage = null;
        $this->editErrorMessage = null;
    }

    /**
     * @return array<string, array<string, string>|string>
     */
    private function editConfigRules(): array
    {
        return match ($this->editProviderType) {
            'sql' => $this->editSqlConfigRules(),
            'filesystem', 'yaml', 'json', 'markdown' => $this->editPathConfigRules(),
            'web' => $this->editWebConfigRules(),
            default => [],
        };
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
    private function editPathConfigRules(): array
    {
        return ['editConfigBasePath' => 'nullable|string|max:512'];
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
    private function buildEditProviderConfig(): array
    {
        return match ($this->editProviderType) {
            'sql' => $this->buildEditSqlConfig(),
            'filesystem', 'yaml', 'json', 'markdown' => empty($this->editConfigBasePath)
                ? []
                : ['basePath' => $this->editConfigBasePath],
            'web' => ['urls' => array_filter(explode("\n", str_replace("\r", '', $this->editConfigUrls)))],
            default => [],
        };
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
                'password' => $this->editConfigPassword !== ''
                    ? Crypt::encryptString($this->editConfigPassword)
                    : '',
            ];
        } else {
            $config['connection'] = $this->editConfigConnectionName;
        }

        return $config;
    }

    /**
     * Store uploaded files for a knowledge source and create Document records.
     *
     * @param  array<int, UploadedFile>  $files
     */
    private function storeUploadedFiles(KnowledgeSource $source, array $files): void
    {
        $type = ProviderType::tryFrom($source->provider_type);

        if (! $type || ! $type->isFilesystemBacked()) {
            return;
        }

        $directoryPath = $type->canonicalPath($source->namespace);

        if (! is_dir($directoryPath) && ! mkdir($directoryPath, 0755, true) && ! is_dir($directoryPath)) {
            throw new \RuntimeException(
                sprintf('Unable to create the directory "%s". Check filesystem permissions.', $directoryPath)
            );
        }

        $records = [];

        foreach ($files as $file) {
            $originalName = preg_replace('/[\/\\\\:*?"<>|]/', '_', $file->getClientOriginalName());
            $uniqueName = sprintf(
                '%s_%s.%s',
                pathinfo($originalName, PATHINFO_FILENAME),
                now()->timestamp.'_'.bin2hex(random_bytes(4)),
                pathinfo($originalName, PATHINFO_EXTENSION)
            );

            $absolutePath = $directoryPath.'/'.$uniqueName;

            // Capture metadata BEFORE the rename — TemporaryUploadedFile reads
            // from Livewire's temp storage which becomes inaccessible after move.
            $sizeBytes = $file->getSize();
            $mimeType = $file->getMimeType() ?: 'application/octet-stream';

            if (! @rename($file->getRealPath(), $absolutePath)) {
                $error = error_get_last();
                throw new \RuntimeException(
                    ($error['message'] ?? 'Could not move file').': '.$file->getRealPath().' → '.$absolutePath
                );
            }

            @chmod($absolutePath, 0664);

            $records[] = [
                'path' => $absolutePath,
                'filename' => $originalName,
                'mime_type' => $mimeType,
                'size_bytes' => $sizeBytes,
                'content_hash' => hash_file('sha256', $absolutePath) ?: '',
                'status' => 'discovered',
            ];
        }

        if (! empty($records)) {
            $source->documents()->createMany($records);

            SyncKnowledgeSource::dispatch($source->id);

            app(PipelineOrchestrator::class)->run($source);
        }
    }

    public function providerTypeLabel(string $type): string
    {
        $pt = ProviderType::tryFrom($type);

        return $pt?->label() ?? ucfirst($type);
    }

    public function getProviderExtensionsLabelProperty(): string
    {
        $type = ProviderType::tryFrom($this->editProviderType);

        return $type?->acceptedFormatsLabel() ?? '';
    }

    public function render(): View
    {
        return view('livewire.admin.knowledge-sources.index', [
            'sources' => KnowledgeSource::orderBy('name')->paginate(15),
        ])->layout('layouts.app', ['header' => 'Knowledge Sources']);
    }
}
