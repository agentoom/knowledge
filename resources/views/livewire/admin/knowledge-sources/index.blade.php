<div>
    <div class="flex justify-between items-center mb-6">
        <flux:heading size="xl">Knowledge Sources</flux:heading>
        <a href="{{ route('admin.knowledge-sources.create') }}" wire:navigate>
            <flux:button icon="plus">
                Add Source
            </flux:button>
        </a>
    </div>

    @if (session()->has('status'))
        <flux:callout color="green" class="mb-4">{{ session('status') }}</flux:callout>
    @endif

    <div class="mb-4">
        <button
            type="button"
            wire:click="$set('showSourceTypesHelp', true)"
            class="inline-flex items-center gap-1.5 text-sm text-blue-600 dark:text-blue-400 hover:underline cursor-pointer"
        >
            <flux:icon name="question-mark-circle" class="size-4" />
            How source types affect retrieval
        </button>
    </div>

    <flux:modal wire:model="showSourceTypesHelp" class="max-w-3xl">
        <x-knowledge.source-types-info />
    </flux:modal>

    @if ($sources->isEmpty())
        <div class="flex flex-col items-center justify-center py-20 text-center">
            <div class="rounded-full bg-zinc-100 dark:bg-zinc-800 p-4 mb-4">
                <flux:icon name="book-open" class="size-10 text-zinc-300 dark:text-zinc-600" />
            </div>
            <h3 class="text-lg font-semibold text-zinc-600 dark:text-zinc-400 mb-1">No knowledge sources yet</h3>
            <p class="text-sm text-zinc-500 dark:text-zinc-500 max-w-md mb-6">
                Create your first knowledge source to start indexing documents, databases, or web content.
            </p>
            <a href="{{ route('admin.knowledge-sources.create') }}" wire:navigate>
                <flux:button icon="plus" variant="primary">Create Knowledge Source</flux:button>
            </a>
        </div>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Name</flux:table.column>
                <flux:table.column>Namespace</flux:table.column>
                <flux:table.column>Type</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Actions</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
            @foreach ($sources as $source)
                <flux:table.row :key="$source->id">
                    <flux:table.cell>
                        <a href="{{ route('admin.knowledge-sources.show', $source->id) }}" class="text-blue-600 hover:underline" wire:navigate>
                            {{ $source->name }}
                        </a>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge>{{ $source->namespace }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <span class="text-sm text-zinc-600 dark:text-zinc-400">
                            {{ $this->providerTypeLabel($source->provider_type) }}
                        </span>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge :color="$source->is_active ? 'green' : 'gray'">
                            {{ $source->is_active ? 'Active' : 'Inactive' }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:button icon="pencil" variant="subtle" size="sm" wire:click="edit({{ $source->id }})">
                            Edit
                        </flux:button>
                        <flux:button icon="trash" variant="subtle" size="sm" color="red" wire:click="delete({{ $source->id }})" wire:confirm="Delete this source?">
                            Delete
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
            </flux:table.rows>
        </flux:table>

        <div class="mt-4">
            {{ $sources->links() }}
        </div>
    @endif

    {{-- Edit Modal --}}
    <flux:modal wire:model="showEditModal" class="max-w-lg">
        {{-- Status / Error messages inside modal --}}
        @if ($editStatusMessage)
            <flux:callout color="green" icon="check-circle" class="mb-4">{{ $editStatusMessage }}</flux:callout>
        @endif
        @if ($editErrorMessage)
            <flux:callout color="red" icon="exclamation-circle" class="mb-4">{{ $editErrorMessage }}</flux:callout>
        @endif

        <form wire:submit="update">
            <flux:heading size="lg">Edit Knowledge Source</flux:heading>

            <div class="mt-4 space-y-4">
                <flux:field>
                    <flux:label>Name</flux:label>
                    <flux:input wire:model="editName" />
                    <flux:error name="editName" />
                </flux:field>

                <flux:field>
                    <flux:label>Namespace</flux:label>
                    <flux:input wire:model="editNamespace" />
                    <flux:error name="editNamespace" />
                </flux:field>

                <flux:field>
                    <flux:label>Provider Type</flux:label>
                    <flux:select wire:model.live="editProviderType">
                        <flux:select.option value="filesystem">Filesystem @if($this->providerExtensionsLabel) ({{ $this->providerExtensionsLabel }}) @endif</flux:select.option>
                        <flux:select.option value="yaml">YAML Files</flux:select.option>
                        <flux:select.option value="json">JSON Files</flux:select.option>
                        <flux:select.option value="markdown">Markdown Files</flux:select.option>
                        <flux:select.option value="generic">Generic (Multi-format)</flux:select.option>
                        <flux:select.option value="sql">SQL Database</flux:select.option>
                        <flux:select.option value="web">Web Crawler</flux:select.option>
                    </flux:select>
                    <flux:error name="editProviderType" />
                </flux:field>

                <flux:field>
                    <flux:label>Description</flux:label>
                    <flux:textarea wire:model="editDescription" />
                </flux:field>

                <flux:field>
                    <flux:checkbox wire:model="editIsActive" label="Active" />
                </flux:field>

                {{-- SQL Provider Config --}}
                @if ($editProviderType === 'sql')
                    <flux:separator />

                    <flux:field>
                        <flux:label>SQL Connection</flux:label>
                        <div class="flex items-center gap-2 mt-1">
                            <flux:checkbox wire:model.live="editConfigUseDynamicConnection" label="Use custom credentials" />
                        </div>
                    </flux:field>

                    @if ($editConfigUseDynamicConnection)
                        <div class="grid grid-cols-2 gap-4">
                            <flux:field>
                                <flux:label>Driver</flux:label>
                                <flux:select wire:model="editConfigDriver">
                                    <flux:select.option value="mysql">MySQL</flux:select.option>
                                    <flux:select.option value="pgsql">PostgreSQL</flux:select.option>
                                    <flux:select.option value="sqlite">SQLite</flux:select.option>
                                    <flux:select.option value="sqlsrv">SQL Server</flux:select.option>
                                </flux:select>
                            </flux:field>
                            <flux:field>
                                <flux:label>Port</flux:label>
                                <flux:input wire:model="editConfigPort" placeholder="3306" />
                            </flux:field>
                        </div>
                        <flux:field>
                            <flux:label>Host</flux:label>
                            <flux:input wire:model="editConfigHost" placeholder="db.example.com" />
                        </flux:field>
                        <div class="grid grid-cols-2 gap-4">
                            <flux:field>
                                <flux:label>Database</flux:label>
                                <flux:input wire:model="editConfigDatabase" placeholder="my_database" />
                            </flux:field>
                            <flux:field>
                                <flux:label>Username</flux:label>
                                <flux:input wire:model="editConfigUsername" placeholder="root" />
                            </flux:field>
                        </div>
                        <flux:field>
                            <flux:label>Password</flux:label>
                            <flux:input type="password" wire:model="editConfigPassword" placeholder="••••••" />
                        </flux:field>
                    @else
                        <flux:field>
                            <flux:label>Connection Name</flux:label>
                            <flux:input wire:model="editConfigConnectionName" placeholder="pgsql" />
                        </flux:field>
                    @endif

                    <flux:field>
                        <flux:label>Table</flux:label>
                        <flux:input wire:model="editConfigTable" placeholder="users" />
                    </flux:field>
                @endif

                {{-- Filesystem-backed types: Upload section in edit modal --}}
                @if (in_array($editProviderType, ['filesystem', 'yaml', 'json', 'markdown']))
                    <flux:separator />

                    {{-- Custom Base Path (optional override) --}}
                    @if (in_array($editProviderType, ['filesystem', 'yaml', 'json', 'markdown']))
                        <flux:field>
                            <flux:label>Custom Base Path (optional)</flux:label>
                            <flux:input wire:model="editConfigBasePath" placeholder="Leave empty to use canonical path" />
                            <flux:description>Override the auto-generated directory path. Leave empty for default.</flux:description>
                        </flux:field>
                    @endif

                    {{-- Existing Files --}}
                    @if (!empty($existingFiles))
                        <flux:field>
                            <flux:label>Uploaded Files</flux:label>
                            <div class="space-y-2 mt-1">
                                @foreach ($existingFiles as $file)
                                    <div class="flex items-center justify-between text-sm bg-zinc-50 dark:bg-zinc-900/50 px-3 py-2 rounded border border-zinc-200 dark:border-zinc-800">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <flux:icon name="document" class="size-4 text-zinc-400 shrink-0" />
                                            <span class="truncate">{{ $file['filename'] }}</span>
                                            <span class="text-zinc-400 shrink-0">({{ number_format($file['size_bytes'] / 1024, 1) }} KB)</span>
                                        </div>
                                        <flux:button
                                            variant="subtle"
                                            size="sm"
                                            color="red"
                                            icon="trash"
                                            wire:click="removeFile({{ $file['id'] }})"
                                            wire:confirm="Remove this file?"
                                        />
                                    </div>
                                @endforeach
                            </div>
                        </flux:field>
                    @endif

                    {{-- Add New Files --}}
                    <flux:field>
                        <flux:label>Add More Files</flux:label>
                        <flux:description>
                            Select files to add.
                            @if ($this->providerExtensionsLabel)
                                Accepted: {{ $this->providerExtensionsLabel }}.
                            @endif
                            For full file management, use the source detail page.
                        </flux:description>
                        <input
                            type="file"
                            wire:model="editUploadedFiles"
                            wire:key="{{ 'edit-file-input-'.$editingId }}"
                            multiple
                            class="mt-2 block w-full text-sm text-zinc-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-zinc-100 file:text-zinc-700 hover:file:bg-zinc-200 dark:file:bg-zinc-800 dark:file:text-zinc-300 dark:hover:file:bg-zinc-700"
                        >
                        <flux:error name="editUploadedFiles" />
                        <flux:error name="editUploadedFiles.*" />
                    </flux:field>

                    @if (!empty($editUploadedFiles))
                        <div class="space-y-2">
                            @foreach ($editUploadedFiles as $index => $file)
                                <div class="flex items-center justify-between text-sm bg-zinc-50 dark:bg-zinc-900/50 px-3 py-2 rounded border border-zinc-200 dark:border-zinc-800">
                                    <div class="flex items-center gap-2">
                                        <flux:icon name="document" class="size-4 text-zinc-400" />
                                        <span>{{ $file->getClientOriginalName() }}</span>
                                        <span class="text-zinc-400">({{ number_format($file->getSize() / 1024, 1) }} KB)</span>
                                    </div>
                                    <flux:button
                                        variant="subtle"
                                        size="sm"
                                        color="red"
                                        icon="x-mark"
                                        wire:click="$removeUpload('editUploadedFiles', '{{ $file->getFilename() }}')"
                                    />
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endif

                {{-- Web Provider Config --}}
                @if ($editProviderType === 'web')
                    <flux:separator />

                    <flux:field>
                        <flux:label>URLs</flux:label>
                        <flux:textarea wire:model="editConfigUrls" rows="4" placeholder="https://docs.example.com&#10;https://api.example.com/reference" />
                        <flux:description>One URL per line.</flux:description>
                        <flux:error name="editConfigUrls" />
                    </flux:field>
                @endif
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <flux:button variant="outline" wire:click="$set('showEditModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save Changes</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
