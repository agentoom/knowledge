<?php

namespace App\Livewire\Admin\KnowledgeSources;

use App\Concerns\StoresKnowledgeFiles;
use App\DocumentPipeline\Services\PipelineOrchestrator;
use App\DocumentPipeline\Services\TikaService;
use App\Jobs\DocumentPipeline\SyncKnowledgeSource;
use App\Knowledge\Enums\ProviderType;
use App\Knowledge\Models\Document;
use App\Knowledge\Models\KnowledgeSource;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * File manager scoped to a knowledge source's directory.
 *
 * Lists existing files (from documents table, with filesystem
 * reconciliation on explicit refresh), allows upload, and deletion.
 */
class FileManager extends Component
{
    use StoresKnowledgeFiles;
    use WithFileUploads;
    use WithPagination;

    public int $sourceId;

    public string $sourceType;

    public string $sourceNamespace;

    public string $directoryPath = '';

    public bool $directoryExists = false;

    /** @var array<int, UploadedFile> */
    public array $uploadingFiles = [];

    public int $uploadProgress = 0;

    public bool $uploading = false;

    /** @var array<int, string> */
    public array $allowedExtensions = [];

    public int $maxUploadSizeKb = 512000;

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    /** @var array<int, array{path: string, filename: string, size: int, is_document: bool, document_id: int|null, document_status: string|null}> */
    public array $files = [];

    private ?LengthAwarePaginator $paginatorInstance = null;

    /** @var array<string, string> */
    public array $sort = ['field' => 'filename', 'direction' => 'asc'];

    public string $filter = 'all';

    public string $search = '';

    protected function rules(): array
    {
        $mimeTypes = $this->resolveAllowedMimeTypes();

        return [
            'uploadingFiles.*' => "file|max:{$this->maxUploadSizeKb}|mimetypes:{$mimeTypes}",
        ];
    }

    /**
     * Resolve the allowed MIME types for validation.
     *
     * When Tika is available this covers office documents, images, ebooks,
     * archives, and email formats. Otherwise it falls back to a smaller set
     * that PHP can handle without external tools.
     */
    private function resolveAllowedMimeTypes(): string
    {
        $tikaAvailable = app(TikaService::class)->isAvailable();

        if ($tikaAvailable) {
            return implode(',', [
                // Plain text & markup
                'text/plain', 'text/html', 'text/csv', 'text/markdown',
                'application/xml', 'text/xml', 'application/json',
                // PDF
                'application/pdf',
                // Microsoft Office
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                // OpenDocument
                'application/vnd.oasis.opendocument.text',
                'application/vnd.oasis.opendocument.spreadsheet',
                'application/vnd.oasis.opendocument.presentation',
                // Rich Text
                'application/rtf', 'text/rtf',
                // E-books
                'application/epub+zip',
                // Images
                'image/jpeg', 'image/png', 'image/gif', 'image/bmp',
                'image/tiff', 'image/webp',
                // Email
                'message/rfc822', 'application/vnd.ms-outlook',
                // Archives
                'application/zip', 'application/x-tar',
                'application/gzip', 'application/x-gzip',
            ]);
        }

        return implode(',', [
            'text/plain', 'text/html', 'text/markdown', 'text/csv',
            'application/pdf', 'application/json',
            'application/x-yaml', 'text/yaml',
            'application/xml', 'text/xml',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    public function mount(int $sourceId, string $sourceType, string $sourceNamespace): void
    {
        $this->sourceId = $sourceId;
        $this->sourceType = $sourceType;
        $this->sourceNamespace = $sourceNamespace;
        $this->maxUploadSizeKb = config('knowledge.max_upload_size_kb', 512000);
        $this->allowedExtensions = $this->resolveAllowedExtensions();
        $this->resolveDirectoryPath();
        $this->refreshFiles();
    }

    public function refreshFiles(): void
    {
        $source = KnowledgeSource::find($this->sourceId);

        if (! $source) {
            $this->files = [];

            return;
        }

        $this->directoryExists = is_dir($this->directoryPath);

        $query = $source->documents()
            ->where('status', '!=', 'stale');

        // Apply search
        if ($this->search !== '' && $this->search !== '0') {
            $query->where('filename', 'ilike', '%'.$this->search.'%');
        }

        // Apply status filter
        if ($this->filter !== 'all') {
            $query->where('status', $this->filter);
        }

        // Apply sort
        $sortField = in_array($this->sort['field'], ['filename', 'size_bytes']) ? $this->sort['field'] : 'filename';
        $query->orderBy($sortField, $this->sort['direction']);

        $paginator = $query->paginate(20);

        $this->paginatorInstance = $paginator;

        $this->files = collect($paginator->items())->map(fn (Document $doc) => [
            'path' => $doc->path,
            'filename' => $doc->filename,
            'size' => $doc->size_bytes ?? 0,
            'is_document' => true,
            'document_id' => $doc->id,
            'document_status' => $doc->status,
        ])->all();
    }

    public function sortBy(string $field): void
    {
        if ($this->sort['field'] === $field) {
            $this->sort['direction'] = $this->sort['direction'] === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = ['field' => $field, 'direction' => 'asc'];
        }

        $this->refreshFiles();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->refreshFiles();
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
        $this->resetPage();
        $this->refreshFiles();
    }

    public function updatedUploadingFiles(): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;

        if (empty($this->uploadingFiles)) {
            $this->uploading = false;

            return;
        }

        try {
            $this->validateOnly('uploadingFiles.*');
        } catch (ValidationException $e) {
            $this->uploading = false;
            $this->uploadProgress = 0;
            $this->uploadingFiles = [];
            $this->errorMessage = collect($e->errors())->flatten()->first() ?: 'Invalid file(s). Please check file type and size.';

            return;
        }

        // Fetch source once for the entire batch.
        $source = KnowledgeSource::findOrFail($this->sourceId);

        $uploaded = 0;
        $errorMessages = [];
        $records = [];

        foreach ($this->uploadingFiles as $index => $file) {
            try {
                $records[] = $this->storeUploadedFile($source, $file);
                $uploaded++;
            } catch (\Throwable $e) {
                $errorMessages[] = '"'.$file->getClientOriginalName().'": '.$e->getMessage();
            }
            $this->uploadProgress = (int) (($index + 1) / count($this->uploadingFiles) * 100);
        }

        // Check for duplicate content hashes before inserting.
        // Merge duplicates from the current batch AND from the database.
        $seenHashes = [];

        $dbHashes = Document::whereNotNull('content_hash')
            ->whereNotIn('status', ['stale', 'duplicate', 'error'])
            ->pluck('content_hash')
            ->unique()
            ->toArray();

        $duplicateCount = 0;
        $records = array_values(array_filter($records, function (array $record) use (&$seenHashes, $dbHashes, &$duplicateCount): bool {
            $hash = $record['content_hash'] ?? '';

            if ($hash === '' || $hash === '0') {
                return true;
            }

            // Check against records already in this batch.
            if (isset($seenHashes[$hash])) {
                $duplicateCount++;

                if (isset($record['path']) && file_exists($record['path'])) {
                    @unlink($record['path']);
                }

                return false;
            }

            // Check against records already in the database.
            if (in_array($hash, $dbHashes, true)) {
                $duplicateCount++;

                if (isset($record['path']) && file_exists($record['path'])) {
                    @unlink($record['path']);
                }

                return false;
            }

            $seenHashes[$hash] = true;

            return true;
        }));

        // Batch-insert all unique Document records at once.
        if (! empty($records)) {
            $source->documents()->createMany($records);
        }

        $this->uploadingFiles = [];
        $this->uploading = false;
        $this->uploadProgress = 0;
        $this->refreshFiles();

        // Defer the expensive filesystem scan to a background job.
        SyncKnowledgeSource::dispatch($this->sourceId);

        // Trigger the full pipeline (parse → normalize → chunk → index)
        // for the newly-uploaded documents.
        if ($uploaded > 0) {
            app(PipelineOrchestrator::class)->run($source);

            $message = $uploaded.' file(s) uploaded.';

            if ($duplicateCount > 0) {
                $message .= " {$duplicateCount} duplicate(s) skipped.";
            }

            $message .= ' Indexing started...';
            $this->statusMessage = $message;
        }

        if (! empty($errorMessages)) {
            $this->errorMessage = 'Failed to upload: '.implode('; ', $errorMessages);
        }
    }

    public function deleteFile(string $filePath): void
    {
        if (! $this->isPathWithinDirectory($filePath)) {
            $this->errorMessage = 'Invalid file path.';

            return;
        }

        $document = KnowledgeSource::findOrFail($this->sourceId)
            ->documents()
            ->where('path', $filePath)
            ->first();

        // Delete the document record — the model's deleting event handles
        // de-indexing chunks and cascade-deleting them from the DB.
        $document?->delete();

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $this->refreshFiles();
        $this->statusMessage = 'File deleted.';
    }

    public function deleteDocument(int $documentId): void
    {
        $document = Document::where('id', $documentId)
            ->where('knowledge_source_id', $this->sourceId)
            ->firstOrFail();

        if ($document->path && $this->isPathWithinDirectory($document->path) && file_exists($document->path)) {
            unlink($document->path);
        }

        $document->delete();
        $this->refreshFiles();
        $this->statusMessage = 'Document removed.';
    }

    public function clearMessages(): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;
    }

    public function getFileCountProperty(): int
    {
        return count($this->files);
    }

    /**
     * @return array{total: int, indexed: int, error: int, duplicate: int, pending: int}
     */
    public function getStatsProperty(): array
    {
        $source = KnowledgeSource::find($this->sourceId);

        if (! $source) {
            return ['total' => 0, 'indexed' => 0, 'error' => 0, 'duplicate' => 0, 'pending' => 0];
        }

        $query = $source->documents()->where('status', '!=', 'stale');

        $total = (clone $query)->count();
        $indexed = (clone $query)->where('status', 'indexed')->count();
        $error = (clone $query)->where('status', 'error')->count();
        $duplicate = (clone $query)->where('status', 'duplicate')->count();
        $pending = (clone $query)->whereNotIn('status', ['indexed', 'error', 'duplicate'])->count();

        return compact('total', 'indexed', 'error', 'duplicate', 'pending');
    }

    /**
     * @return array<int, array{path: string, filename: string, size: int}>
     *
     * @deprecated  Directory scanning is now handled by SyncKnowledgeSource job.
     *              Kept for backward compatibility with the trait.
     */
    private function scanDirectory(): array
    {
        if (! $this->directoryExists) {
            return [];
        }

        $maxDepth = config('knowledge.max_scan_depth', 5);
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directoryPath, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            if ($maxDepth > 0) {
                $relativePath = str_replace($this->directoryPath, '', $file->getPath());
                $depth = substr_count(trim($relativePath, '/'), '/');

                if ($depth >= $maxDepth) {
                    continue;
                }
            }

            $ext = strtolower($file->getExtension());

            if (empty($this->allowedExtensions) || in_array($ext, $this->allowedExtensions)) {
                $files[] = [
                    'path' => $file->getRealPath(),
                    'filename' => $file->getFilename(),
                    'size' => $file->getSize(),
                ];
            }
        }

        return $files;
    }

    /**
     * Move a single uploaded file into the knowledge source directory and
     * return the Document attributes for batch insertion.
     *
     * @return array<string, mixed>
     */
    private function storeUploadedFile(KnowledgeSource $source, UploadedFile $file): array
    {
        if (! $this->directoryExists) {
            if (! mkdir($this->directoryPath, 0755, true) && ! is_dir($this->directoryPath)) {
                throw new \RuntimeException('Failed to create directory: '.$this->directoryPath);
            }
            $this->directoryExists = true;
        }

        $originalName = $this->sanitizeFilename($file->getClientOriginalName());
        $uniqueName = sprintf(
            '%s_%s.%s',
            pathinfo($originalName, PATHINFO_FILENAME),
            now()->timestamp.'_'.bin2hex(random_bytes(4)),
            pathinfo($originalName, PATHINFO_EXTENSION)
        );

        $absolutePath = $this->directoryPath.'/'.$uniqueName;

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

        return [
            'path' => $absolutePath,
            'filename' => $originalName,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
            'content_hash' => hash_file('sha256', $absolutePath) ?: '',
            'status' => 'discovered',
        ];
    }

    private function resolveDirectoryPath(): void
    {
        $type = ProviderType::tryFrom($this->sourceType);

        if ($type && $type->isFilesystemBacked()) {
            $this->directoryPath = $type->canonicalPath($this->sourceNamespace);
        } else {
            $this->directoryPath = '';
        }

        $this->directoryExists = $this->directoryPath !== '' && is_dir($this->directoryPath);
    }

    /**
     * @return array<int, string>
     */
    private function resolveAllowedExtensions(): array
    {
        $type = ProviderType::tryFrom($this->sourceType);

        return $type?->allowedExtensions() ?? [];
    }

    private function isPathWithinDirectory(string $path): bool
    {
        $realBase = realpath($this->directoryPath) ?: $this->directoryPath;
        $realPath = realpath(dirname($path)) ?: dirname($path);

        return str_starts_with($realPath, $realBase) && ! str_contains($path, '..');
    }

    public function getPaginatorProperty(): ?LengthAwarePaginator
    {
        return $this->paginatorInstance;
    }

    public function render(): View
    {
        return view('livewire.admin.knowledge-sources.file-manager');
    }
}
