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
            @if (! $isEditing)
                <flux:button icon="pencil" wire:click="$set('isEditing', true)">Edit</flux:button>
            @endif
            <flux:button icon="arrow-path" wire:click="startSync" wire:loading.attr="disabled">Sync Now</flux:button>
        </div>
    </div>

    @if (session()->has('status'))
        <flux:callout color="green" class="mb-4">{{ session('status') }}</flux:callout>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- Configuration card — switches between view and edit mode --}}
        <flux:card>
            <flux:heading size="lg" class="mb-4">Configuration</flux:heading>

            @if ($isEditing)
                <form wire:submit="save" class="space-y-4">
                    <flux:field>
                        <flux:label>Name</flux:label>
                        <flux:input wire:model="name" />
                        <flux:error name="name" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Namespace</flux:label>
                        <flux:input wire:model="namespace" />
                        <flux:error name="namespace" />
                    </flux:field>

                    <flux:field>
                        <flux:label>
                            Provider Type
                            <button type="button" wire:click="$set('showSourceTypesHelp', true)"
                                class="inline-flex items-center gap-1 ml-1 text-xs font-normal text-blue-600 dark:text-blue-400 hover:underline cursor-pointer">
                                <flux:icon name="question-mark-circle" class="size-3.5" />
                                How it works
                            </button>
                        </flux:label>
                        <flux:select wire:model.live="providerType">
                            <flux:select.option value="filesystem">📄 Filesystem @if($this->acceptedFormatsLabel) ({{ $this->acceptedFormatsLabel }}) @endif</flux:select.option>
                            <flux:select.option value="yaml">📋 YAML Files</flux:select.option>
                            <flux:select.option value="json">{ } JSON Files</flux:select.option>
                            <flux:select.option value="markdown">📝 Markdown Files (.md)</flux:select.option>
                            <flux:select.option value="sql">🗄️ SQL Database</flux:select.option>
                            <flux:select.option value="web">🌐 Web Crawler</flux:select.option>
                        </flux:select>
                        <flux:error name="providerType" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Description (optional)</flux:label>
                        <flux:textarea wire:model="description" rows="2" />
                    </flux:field>

                    <flux:field>
                        <flux:checkbox wire:model="isActive" label="Active" />
                    </flux:field>

                    @if ($providerType === 'sql')
                        <flux:separator />
                        <flux:field>
                            <div class="flex items-center gap-2">
                                <flux:checkbox wire:model.live="configUseDynamicConnection" label="Use custom credentials" />
                            </div>
                        </flux:field>
                        @if ($configUseDynamicConnection)
                            <div class="grid grid-cols-2 gap-4">
                                <flux:field><flux:label>Driver</flux:label><flux:select wire:model="configDriver"><flux:select.option value="mysql">MySQL</flux:select.option><flux:select.option value="pgsql">PostgreSQL</flux:select.option><flux:select.option value="sqlite">SQLite</flux:select.option><flux:select.option value="sqlsrv">SQL Server</flux:select.option></flux:select></flux:field>
                                <flux:field><flux:label>Port</flux:label><flux:input wire:model="configPort" /></flux:field>
                            </div>
                            <flux:field><flux:label>Host</flux:label><flux:input wire:model="configHost" /></flux:field>
                            <div class="grid grid-cols-2 gap-4">
                                <flux:field><flux:label>Database</flux:label><flux:input wire:model="configDatabase" /></flux:field>
                                <flux:field><flux:label>Username</flux:label><flux:input wire:model="configUsername" /></flux:field>
                            </div>
                            <flux:field><flux:label>Password</flux:label><flux:input type="password" wire:model="configPassword" /></flux:field>
                        @else
                            <flux:field><flux:label>Connection Name</flux:label><flux:input wire:model="configConnectionName" /></flux:field>
                        @endif
                        <flux:field><flux:label>Table</flux:label><flux:input wire:model="configTable" /></flux:field>
                    @endif

                    @if (in_array($providerType, ['filesystem', 'yaml', 'json', 'markdown']))
                        <flux:separator />
                        <flux:field>
                            <flux:label>Custom Base Path (optional)</flux:label>
                            <flux:input wire:model="configBasePath" placeholder="Leave empty for canonical path" />
                        </flux:field>
                    @endif

                    @if ($providerType === 'web')
                        <flux:separator />
                        <flux:field>
                            <flux:label>URLs to crawl</flux:label>
                            <flux:textarea wire:model="configUrls" rows="4" />
                            <flux:error name="configUrls" />
                        </flux:field>
                    @endif

                    <div class="flex gap-2 pt-2">
                        <flux:button type="submit" variant="primary">Save Changes</flux:button>
                        <flux:button variant="outline" wire:click="cancelEdit">Cancel</flux:button>
                    </div>
                </form>
            @else
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
                                <span class="text-xs text-zinc-400 dark:text-zinc-500">({{ $this->acceptedFormatsLabel }})</span>
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
                        <p><flux:badge color="{{ $isActive ? 'green' : 'gray' }}">{{ $isActive ? 'Active' : 'Inactive' }}</flux:badge></p>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Created</span>
                        <p>{{ $createdAt ?? 'N/A' }}</p>
                    </div>
                </div>
            @endif
        </flux:card>

        {{-- Provider & Stats --}}
        <flux:card>
            <flux:heading size="lg" class="mb-4">Provider & Stats</flux:heading>
            <div class="space-y-3">
                <div>
                    <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Provider</span>
                    <p>{{ $providerName ?? 'N/A' }}
                        @if ($providerStatus)
                            <flux:badge size="sm" color="{{ $providerStatus === 'active' ? 'green' : 'red' }}">{{ $providerStatus }}</flux:badge>
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

    {{-- Provider Config (hidden while editing the main form) --}}
    @if (! $isEditing)
        <flux:card class="mt-6">
            <div class="flex justify-between items-center mb-4">
                <flux:heading size="lg">Provider Configuration</flux:heading>
                @if (! $isEditingConfig)
                    <flux:button variant="subtle" size="sm" icon="pencil" wire:click="$set('isEditingConfig', true)">Edit Config</flux:button>
                @endif
            </div>

            @if ($isEditingConfig)
                <div class="flex items-center gap-4 mb-4">
                    <flux:label>Editor mode:</flux:label>
                    <div class="flex gap-2">
                        <flux:button size="sm" variant="{{ $useFormEditor ? 'primary' : 'outline' }}" wire:click="$set('useFormEditor', true)">Form</flux:button>
                        <flux:button size="sm" variant="{{ !$useFormEditor ? 'primary' : 'outline' }}" wire:click="$set('useFormEditor', false)">JSON</flux:button>
                    </div>
                </div>

                @if ($useFormEditor)
                    <form wire:submit="saveConfig" class="space-y-4">
                        @if ($providerType === 'sql')
                            <flux:field>
                                <div class="flex items-center gap-2"><flux:checkbox wire:model.live="configUseDynamicConnection" label="Use custom credentials" /></div>
                            </flux:field>
                            @if ($configUseDynamicConnection)
                                <div class="grid grid-cols-2 gap-4">
                                    <flux:field><flux:label>Driver</flux:label><flux:select wire:model="configDriver"><flux:select.option value="mysql">MySQL</flux:select.option><flux:select.option value="pgsql">PostgreSQL</flux:select.option><flux:select.option value="sqlite">SQLite</flux:select.option><flux:select.option value="sqlsrv">SQL Server</flux:select.option></flux:select></flux:field>
                                    <flux:field><flux:label>Port</flux:label><flux:input wire:model="configPort" /></flux:field>
                                </div>
                                <flux:field><flux:label>Host</flux:label><flux:input wire:model="configHost" /></flux:field>
                                <div class="grid grid-cols-2 gap-4">
                                    <flux:field><flux:label>Database</flux:label><flux:input wire:model="configDatabase" /></flux:field>
                                    <flux:field><flux:label>Username</flux:label><flux:input wire:model="configUsername" /></flux:field>
                                </div>
                                <flux:field><flux:label>Password</flux:label><flux:input type="password" wire:model="configPassword" /></flux:field>
                            @else
                                <flux:field><flux:label>Connection Name</flux:label><flux:input wire:model="configConnectionName" /></flux:field>
                            @endif
                            <flux:field><flux:label>Table</flux:label><flux:input wire:model="configTable" /></flux:field>
                        @endif
                        @if (in_array($providerType, ['filesystem', 'yaml', 'json', 'markdown']))
                            <flux:field><flux:label>Custom Base Path (optional)</flux:label><flux:input wire:model="configBasePath" /></flux:field>
                        @endif
                        @if ($providerType === 'web')
                            <flux:field><flux:label>URLs</flux:label><flux:textarea wire:model="configUrls" rows="4" /></flux:field>
                        @endif
                        <div class="flex gap-2">
                            <flux:button type="submit" variant="primary">Save Configuration</flux:button>
                            <flux:button variant="ghost" wire:click="$set('isEditingConfig', false)">Cancel</flux:button>
                        </div>
                    </form>
                @else
                    <form wire:submit="saveConfig" class="space-y-4">
                        <flux:textarea wire:model="configJson" rows="10" class="font-mono text-sm" />
                        <div class="flex gap-2">
                            <flux:button type="submit" variant="primary">Save Configuration</flux:button>
                            <flux:button variant="ghost" wire:click="$set('isEditingConfig', false)">Cancel</flux:button>
                        </div>
                    </form>
                @endif
            @else
                @if (! empty($providerConfig))
                    <pre class="text-sm bg-zinc-50 dark:bg-zinc-900/50 p-4 rounded overflow-x-auto border border-zinc-200 dark:border-zinc-800 text-zinc-800 dark:text-zinc-200">{{ json_encode($this->redactedProviderConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                @else
                    <div class="p-8 text-center bg-zinc-50 dark:bg-zinc-900/50 rounded border border-dashed border-zinc-200 dark:border-zinc-800 text-zinc-400 dark:text-zinc-500">
                        No configuration defined.
                        <flux:button variant="subtle" size="sm" class="mt-2" wire:click="$set('isEditingConfig', true)">Add Configuration</flux:button>
                    </div>
                @endif
            @endif
        </flux:card>
    @endif

    {{-- File Manager --}}
    @if ($showFileManager)
        <div class="mt-6">
            <livewire:admin.knowledge-sources.file-manager
                :sourceId="$sourceId"
                :sourceType="$providerType"
                :sourceNamespace="$namespace"
                :key="'file-manager-'.$sourceId"
            />
        </div>
    @endif

    <flux:modal wire:model="showSourceTypesHelp" class="max-w-3xl">
        <x-knowledge.source-types-info />
    </flux:modal>
</div>
