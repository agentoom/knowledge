<div>
    <div class="flex justify-between items-center mb-6">
        <div>
            <flux:heading size="xl">{{ $name }}</flux:heading>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                {{ $namespace }} &middot; {{ $this->providerTypeLabel }}
                @if ($this->acceptedFormatsLabel)
                    &middot; <span class="text-xs">{{ $this->acceptedFormatsLabel }}</span>
                @endif
            </p>
        </div>
        <div class="flex gap-2">
            <flux:button icon="arrow-path" wire:click="startSync" wire:loading.attr="disabled">
                Sync Now
            </flux:button>
        </div>
    </div>

    @if (session()->has('status'))
        <flux:callout color="green" class="mb-4">{{ session('status') }}</flux:callout>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- Source Details --}}
        <flux:card>
            <flux:heading size="lg" class="mb-4">Configuration</flux:heading>
            <div class="space-y-3">
                <div>
                    <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Name</span>
                    <p>{{ $name }}</p>
                </div>
                <div>
                    <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Namespace</span>
                    <p><flux:badge>{{ $namespace }}</flux:badge></p>
                </div>
                <div>
                    <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Provider Type</span>
                    <p>
                        {{ $this->providerTypeLabel }}
                        @if ($this->acceptedFormatsLabel)
                            <span class="text-xs text-zinc-400 dark:text-zinc-500 ml-1">({{ $this->acceptedFormatsLabel }})</span>
                        @endif
                    </p>
                </div>
                @if ($description)
                <div>
                    <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Description</span>
                    <p>{{ $description }}</p>
                </div>
                @endif
                <div>
                    <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Status</span>
                    <p>
                        <flux:badge color="{{ $isActive ? 'green' : 'gray' }}">
                            {{ $isActive ? 'Active' : 'Inactive' }}
                        </flux:badge>
                    </p>
                </div>
                <div>
                    <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Created</span>
                    <p>{{ $createdAt ?? 'N/A' }}</p>
                </div>
            </div>
        </flux:card>

        {{-- Provider & Stats --}}
        <flux:card>
            <flux:heading size="lg" class="mb-4">Provider & Stats</flux:heading>
            <div class="space-y-3">
                <div>
                    <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Provider</span>
                    <p>{{ $providerName ?? 'N/A' }}
                        @if ($providerStatus)
                            <flux:badge size="sm" color="{{ $providerStatus === 'active' ? 'green' : 'red' }}">
                                {{ $providerStatus }}
                            </flux:badge>
                        @endif
                    </p>
                </div>
                <div>
                    <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Documents</span>
                    <p>{{ $documentCount }}</p>
                </div>
                <div>
                    <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Indexed Documents</span>
                    <p>{{ $activeDocumentCount }}</p>
                </div>
            </div>
        </flux:card>
    </div>

    {{-- File Manager (for filesystem-backed sources) --}}
    @if ($showFileManager)
        <div class="mb-6">
            <livewire:admin.knowledge-sources.file-manager
                :sourceId="$sourceId"
                :sourceType="$providerType"
                :sourceNamespace="$namespace"
                :key="'file-manager-'.$sourceId"
            />
        </div>
    @endif

    {{-- Provider Config --}}
    <flux:card class="mt-6">
        <div class="flex justify-between items-center mb-4">
            <flux:heading size="lg">Provider Configuration</flux:heading>
            @if (!$isEditingConfig)
                <flux:button variant="subtle" size="sm" icon="pencil" wire:click="$set('isEditingConfig', true)">Edit Config</flux:button>
            @endif
        </div>

        @if ($isEditingConfig)
            <div class="flex items-center gap-4 mb-4">
                <flux:label>Editor mode:</flux:label>
                <div class="flex gap-2">
                    <flux:button
                        size="sm"
                        variant="{{ $useFormEditor ? 'primary' : 'outline' }}"
                        wire:click="$set('useFormEditor', true)"
                    >Form</flux:button>
                    <flux:button
                        size="sm"
                        variant="{{ !$useFormEditor ? 'primary' : 'outline' }}"
                        wire:click="$set('useFormEditor', false)"
                    >JSON</flux:button>
                </div>
            </div>

            @if ($useFormEditor)
                <form wire:submit="saveConfig" class="space-y-4">
                    @if ($providerType === 'sql')
                        <flux:separator />

                        <flux:field>
                            <flux:label>SQL Connection</flux:label>
                            <div class="flex items-center gap-2 mt-1">
                                <flux:checkbox wire:model.live="configUseDynamicConnection" label="Use custom credentials" />
                            </div>
                        </flux:field>

                        @if ($configUseDynamicConnection)
                            <div class="grid grid-cols-2 gap-4">
                                <flux:field>
                                    <flux:label>Driver</flux:label>
                                    <flux:select wire:model="configDriver">
                                        <flux:select.option value="mysql">MySQL</flux:select.option>
                                        <flux:select.option value="pgsql">PostgreSQL</flux:select.option>
                                        <flux:select.option value="sqlite">SQLite</flux:select.option>
                                        <flux:select.option value="sqlsrv">SQL Server</flux:select.option>
                                    </flux:select>
                                </flux:field>
                                <flux:field>
                                    <flux:label>Port</flux:label>
                                    <flux:input wire:model="configPort" placeholder="3306" />
                                </flux:field>
                            </div>
                            <flux:field>
                                <flux:label>Host</flux:label>
                                <flux:input wire:model="configHost" placeholder="db.example.com" />
                            </flux:field>
                            <div class="grid grid-cols-2 gap-4">
                                <flux:field>
                                    <flux:label>Database</flux:label>
                                    <flux:input wire:model="configDatabase" placeholder="my_database" />
                                </flux:field>
                                <flux:field>
                                    <flux:label>Username</flux:label>
                                    <flux:input wire:model="configUsername" placeholder="root" />
                                </flux:field>
                            </div>
                            <flux:field>
                                <flux:label>Password</flux:label>
                                <flux:input type="password" wire:model="configPassword" placeholder="••••••" />
                            </flux:field>
                        @else
                            <flux:field>
                                <flux:label>Connection Name</flux:label>
                                <flux:input wire:model="configConnectionName" placeholder="pgsql" />
                            </flux:field>
                        @endif

                        <flux:field>
                            <flux:label>Table</flux:label>
                            <flux:input wire:model="configTable" placeholder="users" />
                        </flux:field>
                    @endif

                    @if (in_array($providerType, ['filesystem', 'yaml', 'json', 'markdown']))
                        <flux:field>
                            <flux:label>Custom Base Path (optional)</flux:label>
                            <flux:input wire:model="configBasePath" placeholder="Leave empty for canonical path" />
                            <flux:description>Override the auto-generated directory path.</flux:description>
                        </flux:field>
                    @endif

                    @if ($providerType === 'web')
                        <flux:field>
                            <flux:label>URLs</flux:label>
                            <flux:textarea wire:model="configUrls" rows="4" placeholder="https://docs.example.com&#10;https://api.example.com/reference" />
                            <flux:description>One URL per line.</flux:description>
                        </flux:field>
                    @endif

                    <div class="flex gap-2">
                        <flux:button type="submit" variant="primary">Save Configuration</flux:button>
                        <flux:button variant="ghost" wire:click="$set('isEditingConfig', false)">Cancel</flux:button>
                    </div>
                </form>
            @else
                <form wire:submit="saveConfig" class="space-y-4">
                    <flux:field>
                        <flux:label>Configuration (JSON)</flux:label>
                        <flux:textarea wire:model="configJson" rows="10" class="font-mono text-sm" />
                        <flux:error name="configJson" />
                    </flux:field>
                    <div class="flex gap-2">
                        <flux:button type="submit" variant="primary">Save Configuration</flux:button>
                        <flux:button variant="ghost" wire:click="$set('isEditingConfig', false)">Cancel</flux:button>
                    </div>
                </form>
            @endif
        @else
            @if (!empty($providerConfig))
                <pre class="text-sm bg-zinc-50 dark:bg-zinc-900/50 p-4 rounded overflow-x-auto border border-zinc-200 dark:border-zinc-800 text-zinc-800 dark:text-zinc-200">{{ json_encode($this->redactedProviderConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            @else
                <div class="p-8 text-center bg-zinc-50 dark:bg-zinc-900/50 rounded border border-dashed border-zinc-200 dark:border-zinc-800 text-zinc-400 dark:text-zinc-500">
                    No configuration defined.
                    <flux:button variant="subtle" size="sm" class="mt-2" wire:click="$set('isEditingConfig', true)">Add Configuration</flux:button>
                </div>
            @endif
        @endif
    </flux:card>

    {{-- Recent Documents --}}
    @if (!empty($documents))
        <flux:heading size="lg" class="mt-6 mb-4">Recent Documents</flux:heading>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Filename</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Size</flux:table.column>
                <flux:table.column>Created</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($documents as $doc)
                    <flux:table.row :key="$doc['id']">
                        <flux:table.cell>
                            <a href="{{ route('admin.documents.show', $doc['id']) }}" class="text-blue-600 hover:underline">
                                {{ $doc['filename'] }}
                            </a>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="{{ $doc['status'] === 'indexed' ? 'green' : ($doc['status'] === 'error' ? 'red' : 'gray') }}">
                                {{ $doc['status'] }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ number_format($doc['size_bytes']) }} bytes</flux:table.cell>
                        <flux:table.cell>{{ $doc['created_at'] }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
