<div>
    <flux:heading size="xl" class="mb-6">Document: {{ $filename }}</flux:heading>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Document Metadata --}}
        <flux:card>
            <flux:heading size="lg" class="mb-4">Document Details</flux:heading>
            <div class="space-y-3">
                <div>
                    <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Filename</span>
                    <p>{{ $filename }}</p>
                </div>
                <div>
                    <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Status</span>
                    <p>
                        <flux:badge color="{{ match($status) { 'indexed' => 'green', 'parsed', 'chunked' => 'blue', 'discovered' => 'yellow', 'duplicate' => 'orange', 'error' => 'red', default => 'gray' } }}">
                            {{ $status }}
                        </flux:badge>
                    </p>
                </div>
                @if ($sourceName)
                <div>
                    <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Knowledge Source</span>
                    <p>{{ $sourceName }}</p>
                </div>
                @endif
                @if ($mimeType)
                <div>
                    <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">MIME Type</span>
                    <p>{{ $mimeType }}</p>
                </div>
                @endif
                @if ($sizeBytes)
                <div>
                    <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Size</span>
                    <p>{{ number_format($sizeBytes) }} bytes</p>
                </div>
                @endif
                @if ($path)
                <div>
                    <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Path</span>
                    <p class="text-sm font-mono text-zinc-500 dark:text-zinc-400">{{ $path }}</p>
                </div>
                @endif
            </div>
        </flux:card>

        {{-- Processing Timeline --}}
        <flux:card>
            <flux:heading size="lg" class="mb-4">Processing Timeline</flux:heading>
            <div class="space-y-3">
                <div>
                    <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Created</span>
                    <p>{{ $createdAt ?? 'N/A' }}</p>
                </div>
                <div>
                    <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Parsed</span>
                    <p>{{ $parsedAt ?? 'Not yet' }}</p>
                </div>
                <div>
                    <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Chunked</span>
                    <p>{{ $chunkedAt ?? 'Not yet' }}</p>
                </div>
                <div>
                    <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Indexed</span>
                    <p>{{ $indexedAt ?? 'Not yet' }}</p>
                </div>
            </div>
        </flux:card>
    </div>

    {{-- Reprocess flash --}}
    @if (session('status'))
        <flux:callout color="blue" icon="information-circle" class="mt-6">{{ session('status') }}</flux:callout>
    @endif

    {{-- Error Message --}}
    @if ($errorMessage)
        <div class="mt-6 flex items-start justify-between gap-4">
            <flux:callout color="red" class="flex-1">{{ $errorMessage }}</flux:callout>
            @if ($status === 'error' && $sourceType !== 'web')
                <flux:button
                    variant="outline"
                    icon="arrow-path"
                    wire:click="reprocess"
                    wire:confirm="Queue this document for reprocessing?"
                >
                    Reprocess
                </flux:button>
            @endif
        </div>
    @endif

    {{-- Content --}}
    @if ($content)
        <flux:card class="mt-6">
            <flux:heading size="lg" class="mb-4">Content</flux:heading>
            <pre class="text-sm bg-zinc-50 dark:bg-zinc-900/50 p-4 rounded max-h-96 overflow-y-auto border border-zinc-200 dark:border-zinc-800 text-zinc-800 dark:text-zinc-200">{{ $content }}</pre>
        </flux:card>
    @endif

    {{-- Chunks --}}
    @if (!empty($chunks))
        <flux:heading size="lg" class="mt-6 mb-4">Chunks ({{ count($chunks) }})</flux:heading>
        <div class="space-y-3">
            @foreach ($chunks as $chunk)
                <flux:card>
                    <div class="flex items-center justify-between mb-2">
                        <flux:badge size="sm">Chunk #{{ $chunk['sequence'] }}</flux:badge>
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $chunk['token_count'] }} tokens</span>
                    </div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $chunk['content_preview'] }}</p>
                </flux:card>
            @endforeach
        </div>
    @endif
</div>
