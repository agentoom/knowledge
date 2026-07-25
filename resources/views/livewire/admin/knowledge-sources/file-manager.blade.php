<div
    class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 overflow-hidden"
    x-data="{
        dragOver: false,
        handleDrop(e) {
            this.dragOver = false;
            if (!e.dataTransfer.files.length) return;
            $wire.uploadMultiple('uploadingFiles', Array.from(e.dataTransfer.files),
                () => {}, // success - handled by updatedUploadingFiles hook
                () => { $wire.set('errorMessage', 'Upload failed. Please try again.'); },
                (event) => { $wire.set('uploadProgress', event.detail.progress); $wire.set('uploading', true); },
            );
        },
        handleDragOver(e) {
            this.dragOver = true;
        },
        handleDragLeave() {
            this.dragOver = false;
        },
        handleFileSelect(el) {
            if (!el.files.length) return;
            $wire.uploadMultiple('uploadingFiles', Array.from(el.files),
                () => {}, // success - handled by updatedUploadingFiles hook
                () => { $wire.set('errorMessage', 'Upload failed. Please try again.'); },
                (event) => { $wire.set('uploadProgress', event.detail.progress); $wire.set('uploading', true); },
            );
            el.value = '';
        }
    }"
>
    {{-- Header with stats --}}
    <div class="px-6 py-4 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="lg">Files</flux:heading>
                <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">
                    {{ $this->stats['total'] }} file{{ $this->stats['total'] !== 1 ? 's' : '' }}
                    &middot;
                    <span class="text-green-600 dark:text-green-400">{{ $this->stats['indexed'] }} indexed</span>
                    @if ($this->stats['error'] > 0)
                        &middot;
                        <span class="text-red-600 dark:text-red-400">{{ $this->stats['error'] }} error{{ $this->stats['error'] !== 1 ? 's' : '' }}</span>
                    @endif
                    @if ($this->stats['discovered'] > 0)
                        &middot;
                        <span class="text-amber-600 dark:text-amber-400">{{ $this->stats['discovered'] }} pending</span>
                    @endif
                </p>
            </div>

            <div class="flex items-center gap-2">
                <flux:button
                    variant="ghost"
                    size="sm"
                    icon="arrow-path"
                    wire:click="refreshFiles"
                >
                    Refresh
                </flux:button>
            </div>
        </div>
    </div>

    {{-- Upload drop zone --}}
    <div
        class="relative"
        x-on:dragover.prevent="handleDragOver"
        x-on:dragleave.prevent="handleDragLeave"
        x-on:drop.prevent="handleDrop"
    >
        {{-- Drop zone overlay --}}
        <div
            x-show="dragOver"
            x-cloak
            class="absolute inset-0 z-50 flex items-center justify-center bg-blue-500/10 dark:bg-blue-400/10 border-2 border-dashed border-blue-500 dark:border-blue-400 rounded-xl backdrop-blur-sm"
        >
            <div class="text-center p-8">
                <flux:icon name="arrow-down-tray" class="size-12 text-blue-500 dark:text-blue-400 mx-auto mb-3" />
                <p class="text-lg font-semibold text-blue-600 dark:text-blue-400">Drop files to upload</p>
                <p class="text-sm text-blue-500 dark:text-blue-400/80 mt-1">
                    Files will be added to this knowledge source
                </p>
            </div>
        </div>

        {{-- File upload area --}}
        <div class="px-6 py-4 border-b border-zinc-200 dark:border-zinc-700">
            <div class="flex items-center gap-3">
                <input
                    type="file"
                    x-ref="fileInput"
                    x-on:change="handleFileSelect($el)"
                    multiple
                    class="block w-full text-sm text-zinc-500
                        file:mr-4 file:py-2 file:px-4
                        file:rounded-md file:border-0
                        file:text-sm file:font-semibold
                        file:bg-zinc-100 file:text-zinc-700
                        hover:file:bg-zinc-200
                        dark:file:bg-zinc-800 dark:file:text-zinc-300
                        dark:hover:file:bg-zinc-700
                        file:cursor-pointer file:transition-colors
                        cursor-pointer"
                >
            </div>
            <p class="text-xs text-zinc-400 dark:text-zinc-500 mt-2">
                @php
                    $allExtensions = $this->allowedExtensions;
                    $shown = array_slice($allExtensions, 0, 15);
                    $remaining = count($allExtensions) - count($shown);
                @endphp
                Accepted: {{ implode(', ', $shown) }}
                @if ($remaining > 0)
                    +{{ $remaining }} more
                @endif
                &middot; Max {{ round($maxUploadSizeKb / 1024) }} MB.
                @if (count($allExtensions) > 12)
                    <br>Images are indexed for metadata only (filename, dimensions, EXIF).
                @endif
                You can also drag &amp; drop files onto this area.
            </p>
        </div>

        {{-- Status / Error messages --}}
        @if ($statusMessage)
            <div class="px-6 py-3 border-b border-zinc-200 dark:border-zinc-700 bg-green-50 dark:bg-green-950/30 flex items-center justify-between">
                <div class="flex items-center gap-2 text-sm text-green-700 dark:text-green-300">
                    <flux:icon name="check-circle" class="size-4" />
                    {{ $statusMessage }}
                </div>
                <button wire:click="clearMessages" class="text-green-500 hover:text-green-700 dark:hover:text-green-300">
                    <flux:icon name="x-mark" class="size-4" />
                </button>
            </div>
        @endif

        @if ($errorMessage)
            <div class="px-6 py-3 border-b border-zinc-200 dark:border-zinc-700 bg-red-50 dark:bg-red-950/30 flex items-center justify-between">
                <div class="flex items-center gap-2 text-sm text-red-700 dark:text-red-300">
                    <flux:icon name="exclamation-circle" class="size-4" />
                    {{ $errorMessage }}
                </div>
                <button wire:click="clearMessages" class="text-red-500 hover:text-red-700 dark:hover:text-red-300">
                    <flux:icon name="x-mark" class="size-4" />
                </button>
            </div>
        @endif

        {{-- Validation errors --}}
        @error('uploadingFiles.*')
            <div class="px-6 py-3 border-b border-zinc-200 dark:border-zinc-700 bg-red-50 dark:bg-red-950/30">
                <div class="flex items-center gap-2 text-sm text-red-700 dark:text-red-300">
                    <flux:icon name="exclamation-circle" class="size-4" />
                    {{ $message }}
                </div>
            </div>
        @enderror

        {{-- Upload progress bar --}}
        @if ($uploading)
            <div class="px-6 py-4 border-b border-zinc-200 dark:border-zinc-700 bg-blue-50 dark:bg-blue-950/30">
                <div class="flex items-center gap-3">
                    <flux:icon name="arrow-path" class="size-5 text-blue-600 dark:text-blue-400 animate-spin" />
                    <div class="flex-1">
                        <div class="flex justify-between text-sm mb-1">
                            <span class="font-medium text-blue-700 dark:text-blue-300">Uploading...</span>
                            <span class="text-blue-600 dark:text-blue-400">{{ $uploadProgress }}%</span>
                        </div>
                        <div class="w-full bg-blue-200 dark:bg-blue-900 rounded-full h-2">
                            <div
                                class="bg-blue-600 dark:bg-blue-400 h-2 rounded-full transition-all duration-300"
                                style="width: {{ $uploadProgress }}%"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Filter bar --}}
        <div class="px-6 py-3 border-b border-zinc-100 dark:border-zinc-800 flex items-center gap-2 flex-wrap">
            <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Filter:</span>
            @foreach ([
                'all' => 'All',
                'indexed' => 'Indexed',
                'discovered' => 'Pending',
                'error' => 'Errors',
                'physical' => 'Disk Only',
            ] as $value => $label)
                <flux:button
                    size="sm"
                    variant="{{ $filter === $value ? 'primary' : 'ghost' }}"
                    wire:click="setFilter('{{ $value }}')"
                >
                    {{ $label }}
                </flux:button>
            @endforeach
        </div>

        {{-- File listing --}}
        @if (empty($files))
            <div class="flex flex-col items-center justify-center py-20 px-6 text-center">
                <div class="rounded-full bg-zinc-100 dark:bg-zinc-800 p-4 mb-4">
                    <flux:icon name="document" class="size-10 text-zinc-300 dark:text-zinc-600" />
                </div>
                <h3 class="text-lg font-semibold text-zinc-600 dark:text-zinc-400 mb-1">No files yet</h3>
                <p class="text-sm text-zinc-500 dark:text-zinc-500 max-w-sm">
                    @if ($directoryExists)
                        Upload files via the input above, or place them directly in:
                        <code class="mt-2 block text-xs bg-zinc-100 dark:bg-zinc-800 px-3 py-1.5 rounded font-mono break-all">
                            {{ $directoryPath }}
                        </code>
                    @else
                        The directory will be created when you upload your first file.
                    @endif
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="text-left px-6 py-3 text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider cursor-pointer hover:text-zinc-700 dark:hover:text-zinc-300 select-none"
                                wire:click="sortBy('filename')">
                                <div class="flex items-center gap-1">
                                    Name
                                    @if ($sort['field'] === 'filename')
                                        <flux:icon name="{{ $sort['direction'] === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="size-3.5" />
                                    @endif
                                </div>
                            </th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider cursor-pointer hover:text-zinc-700 dark:hover:text-zinc-300 select-none hidden sm:table-cell"
                                wire:click="sortBy('size')">
                                <div class="flex items-center gap-1">
                                    Size
                                    @if ($sort['field'] === 'size')
                                        <flux:icon name="{{ $sort['direction'] === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="size-3.5" />
                                    @endif
                                </div>
                            </th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider hidden md:table-cell">
                                Status
                            </th>
                            <th class="text-right px-6 py-3 text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($files as $file)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors group">
                                {{-- Filename --}}
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="shrink-0">
                                            {!! $this->iconForFile($file['filename']) !!}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-zinc-800 dark:text-zinc-200 truncate max-w-[200px] sm:max-w-[300px] lg:max-w-[400px]">
                                                {{ $file['filename'] }}
                                            </p>
                                            <p class="text-xs text-zinc-400 dark:text-zinc-500 sm:hidden">
                                                {{ $this->formatSize($file['size']) }}
                                                @if (!empty($file['document_status']))
                                                    &middot; {{ $file['document_status'] }}
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                {{-- Size --}}
                                <td class="px-6 py-3 text-sm text-zinc-600 dark:text-zinc-400 hidden sm:table-cell whitespace-nowrap">
                                    {{ $this->formatSize($file['size']) }}
                                </td>

                                {{-- Status --}}
                                <td class="px-6 py-3 hidden md:table-cell">
                                    @php
                                        $status = $file['document_status'] ?? null;
                                    @endphp
                                    @if ($status === 'indexed')
                                        <flux:badge size="sm" color="green" icon="check-circle">Indexed</flux:badge>
                                    @elseif ($status === 'error')
                                        <flux:badge size="sm" color="red" icon="exclamation-circle">Error</flux:badge>
                                    @elseif (in_array($status, ['discovered', 'parsed', 'chunked']))
                                        <flux:badge size="sm" color="amber" icon="clock">{{ ucfirst($status) }}</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="zinc">On Disk</flux:badge>
                                    @endif
                                </td>

                                {{-- Actions --}}
                                <td class="px-6 py-3 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        @if (!empty($file['document_id']))
                                            <a
                                                href="{{ route('admin.documents.show', $file['document_id']) }}"
                                                wire:navigate
                                                class="inline-flex items-center justify-center size-8 rounded-md text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700"
                                                title="View document"
                                            >
                                                <flux:icon name="eye" class="size-4" />
                                            </a>
                                        @endif
                                        <button
                                            wire:click="deleteFile('{{ $file['path'] }}')"
                                            wire:confirm="Delete '{{ $file['filename'] }}'? This cannot be undone."
                                            class="inline-flex items-center justify-center size-8 rounded-md text-zinc-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20"
                                            title="Delete file"
                                        >
                                            <flux:icon name="trash" class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Footer with directory info --}}
    @if ($directoryExists)
        <div class="px-6 py-3 border-t border-zinc-100 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-800/30">
            <div class="flex items-center gap-2 text-xs text-zinc-400 dark:text-zinc-500">
                <flux:icon name="folder" class="size-3.5" />
                <code class="font-mono truncate">{{ $directoryPath }}</code>
            </div>
        </div>
    @endif
</div>
