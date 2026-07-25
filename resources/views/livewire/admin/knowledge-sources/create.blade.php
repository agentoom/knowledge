<div class="max-w-4xl mx-auto">
    {{-- Wizard Steps Indicator --}}
    <div class="mb-8">
        <nav aria-label="Progress" class="flex items-center justify-center">
            <ol role="list" class="flex items-center gap-1">
                @foreach ([
                    1 => 'Configure',
                    2 => 'Review & Create',
                    3 => 'Upload Files',
                ] as $num => $label)
                    <li class="flex items-center">
                        @if ($loop->index > 0)
                            <div class="w-8 sm:w-12 h-px {{ $step > $num ? 'bg-blue-600 dark:bg-blue-400' : 'bg-zinc-300 dark:bg-zinc-600' }}"></div>
                        @endif
                        <button
                            type="button"
                            wire:click="goToStep({{ $num }})"
                            @class([
                                'relative flex items-center justify-center rounded-full transition-colors',
                                'size-8 sm:size-10 text-sm font-semibold',
                                'bg-blue-600 text-white dark:bg-blue-500' => $step === $num,
                                'bg-blue-600 text-white dark:bg-blue-500 cursor-pointer hover:bg-blue-700' => $step > $num,
                                'bg-zinc-200 text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400' => $step < $num,
                            ])
                            @disabled($step < $num)
                        >
                            @if ($step > $num)
                                <flux:icon name="check" class="size-4 sm:size-5" />
                            @else
                                <span>{{ $num }}</span>
                            @endif
                        </button>
                        <span class="hidden sm:block ml-3 text-sm font-medium {{ $step >= $num ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-400 dark:text-zinc-500' }}">
                            {{ $label }}
                        </span>
                    </li>
                @endforeach
            </ol>
        </nav>
    </div>

    {{-- Step 1: Configure --}}
    @if ($step === 1)
        <flux:card class="p-6 sm:p-8">
            <form wire:submit="nextStep">
                <div class="mb-6">
                    <flux:heading size="xl">Configure Knowledge Source</flux:heading>
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                        Choose a name, namespace, and the type of data you want to index.
                    </p>
                </div>

                <div class="space-y-6">
                    {{-- Name --}}
                    <flux:field>
                        <flux:label>Name</flux:label>
                        <flux:input wire:model="name" placeholder="My Documentation" class="max-w-lg" />
                        <flux:description>A human-readable name for this knowledge source.</flux:description>
                        <flux:error name="name" />
                    </flux:field>

                    {{-- Namespace --}}
                    <flux:field>
                        <flux:label>Namespace</flux:label>
                        <flux:input wire:model="namespace" placeholder="my-docs" class="max-w-lg" />
                        <flux:description>
                            A unique slug used as the folder name and API identifier.
                            Only lowercase letters, numbers, hyphens, and underscores.
                        </flux:description>
                        <flux:error name="namespace" />
                    </flux:field>

                    {{-- Provider Type --}}
                    <flux:field>
                        <flux:label>
                            Provider Type
                            <button
                                type="button"
                                wire:click="$set('showSourceTypesHelp', true)"
                                class="inline-flex items-center gap-1 ml-1 text-xs font-normal text-blue-600 dark:text-blue-400 hover:underline cursor-pointer"
                            >
                                <flux:icon name="question-mark-circle" class="size-3.5" />
                                How it works
                            </button>
                        </flux:label>
                        <flux:select wire:model.live="providerType" class="max-w-lg">
                            <flux:select.option value="filesystem">📄 Filesystem @if($this->providerExtensionsLabel) ({{ $this->providerExtensionsLabel }}) @endif</flux:select.option>
                            <flux:select.option value="yaml">📋 YAML Files</flux:select.option>
                            <flux:select.option value="json">{ } JSON Files</flux:select.option>
                            <flux:select.option value="markdown">📝 Markdown Files (.md)</flux:select.option>
                            <flux:select.option value="sql">🗄️ SQL Database</flux:select.option>
                            <flux:select.option value="web">🌐 Web Crawler</flux:select.option>
                        </flux:select>
                        <flux:description>
                            Determines how files are discovered, stored, and searched.
                            Filesystem-backed types support both server-side placement and UI uploads.
                        </flux:description>
                        <flux:error name="providerType" />
                    </flux:field>

                    {{-- Source types help modal --}}
                    <flux:modal wire:model="showSourceTypesHelp" class="max-w-3xl">
                        <x-knowledge.source-types-info />
                    </flux:modal>

                    {{-- Description --}}
                    <flux:field>
                        <flux:label>Description (optional)</flux:label>
                        <flux:textarea wire:model="description" rows="2" placeholder="Brief description of this source..." class="max-w-lg" />
                        <flux:error name="description" />
                    </flux:field>

                    {{-- SQL Provider Config (shown inline for SQL type) --}}
                    @if ($providerType === 'sql')
                        <flux:separator />

                        <div class="space-y-4">
                            <flux:heading size="sm">Database Connection</flux:heading>

                            <flux:field>
                                <div class="flex items-center gap-2">
                                    <flux:checkbox wire:model.live="configUseDynamicConnection" label="Use custom credentials" />
                                </div>
                                <flux:description>
                                    Enable to enter connection details directly. Otherwise, use a named connection.
                                </flux:description>
                            </flux:field>

                            @if ($configUseDynamicConnection)
                                <div class="grid grid-cols-2 gap-4 max-w-lg">
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
                                    <flux:input wire:model="configHost" placeholder="db.example.com" class="max-w-lg" />
                                </flux:field>
                                <div class="grid grid-cols-2 gap-4 max-w-lg">
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
                                    <flux:input type="password" wire:model="configPassword" placeholder="••••••" class="max-w-lg" />
                                </flux:field>
                            @else
                                <flux:field>
                                    <flux:label>Connection Name</flux:label>
                                    <flux:input wire:model="configConnectionName" placeholder="pgsql" class="max-w-lg" />
                                    <flux:description>Named connection from config/database.php</flux:description>
                                </flux:field>
                            @endif

                            <flux:field>
                                <flux:label>Table</flux:label>
                                <flux:input wire:model="configTable" placeholder="users" class="max-w-lg" />
                                <flux:description>The database table to search.</flux:description>
                            </flux:field>
                        </div>
                    @endif

                    {{-- Web Provider Config --}}
                    @if ($providerType === 'web')
                        <flux:separator />

                        <flux:field>
                            <flux:label>URLs to crawl</flux:label>
                            <flux:textarea wire:model="configUrls" rows="4" placeholder="https://docs.example.com&#10;https://api.example.com/reference" class="max-w-lg" />
                            <flux:description>One URL per line.</flux:description>
                            <flux:error name="configUrls" />
                        </flux:field>
                    @endif
                </div>

                <div class="mt-8 flex justify-end">
                    <flux:button type="submit" variant="primary" icon="arrow-right" trailing>
                        Next: Review & Create
                    </flux:button>
                </div>
            </form>
        </flux:card>
    @endif

    {{-- Step 2: Review & Create --}}
    @if ($step === 2)
        <flux:card class="p-6 sm:p-8">
            <div class="mb-6">
                <flux:heading size="xl">Review & Create</flux:heading>
                <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                    Review your configuration before creating the knowledge source.
                </p>
            </div>

            <div class="space-y-6">
                {{-- Summary card --}}
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                    <div class="px-5 py-4 bg-zinc-50 dark:bg-zinc-800/50 space-y-3">
                        <div class="flex justify-between items-start">
                            <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Name</span>
                            <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $name }}</span>
                        </div>
                        <div class="flex justify-between items-start">
                            <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Namespace</span>
                            <code class="text-sm font-mono text-zinc-700 dark:text-zinc-300">{{ $namespace }}</code>
                        </div>
                        <div class="flex justify-between items-start">
                            <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Type</span>
                            <flux:badge size="sm">{{ $this->providerLabel }}</flux:badge>
                        </div>
                        @if ($description)
                            <div class="flex justify-between items-start">
                                <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Description</span>
                                <span class="text-sm text-zinc-700 dark:text-zinc-300 text-right max-w-xs">{{ $description }}</span>
                            </div>
                        @endif

                        {{-- Directory path (filesystem-backed types) --}}
                        @if ($directoryPath)
                            <flux:separator />
                            <div class="flex justify-between items-start">
                                <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Directory</span>
                                <div class="text-right">
                                    <code class="text-xs font-mono text-zinc-600 dark:text-zinc-400 break-all block">{{ $directoryPath }}</code>
                                    <p class="text-xs mt-1 {{ $directoryExists ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400' }}">
                                        {{ $directoryExists ? '✓ Directory already exists' : '⟳ Will be created on confirmation' }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex justify-between items-start">
                                <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Allowed Extensions</span>
                                <span class="text-xs text-zinc-600 dark:text-zinc-400">
                                    {{ implode(', ', $allowedExtensions) }}
                                </span>
                            </div>
                        @endif

                        {{-- SQL-specific --}}
                        @if ($providerType === 'sql')
                            <flux:separator />
                            <div class="flex justify-between items-start">
                                <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Connection</span>
                                <span class="text-sm text-zinc-700 dark:text-zinc-300">
                                    {{ $configUseDynamicConnection ? $configDriver.' @ '.$configHost : $configConnectionName }}
                                </span>
                            </div>
                            <div class="flex justify-between items-start">
                                <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Table</span>
                                <code class="text-sm font-mono text-zinc-700 dark:text-zinc-300">{{ $configTable }}</code>
                            </div>
                        @endif

                        {{-- Web-specific --}}
                        @if ($providerType === 'web')
                            <flux:separator />
                            <div class="flex justify-between items-start">
                                <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">URLs</span>
                                <div class="text-right text-sm text-zinc-700 dark:text-zinc-300">
                                    @foreach (explode("\n", str_replace("\r", '', $configUrls)) as $url)
                                        @if (!empty(trim($url)))
                                            <div>{{ trim($url) }}</div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="mt-8 flex justify-between">
                <flux:button variant="outline" wire:click="previousStep" icon="arrow-left">
                    Back
                </flux:button>
                <flux:button variant="primary" wire:click="nextStep" icon="check">
                    Create Knowledge Source
                </flux:button>
            </div>
        </flux:card>
    @endif

    {{-- Step 3: Upload Files --}}
    @if ($step === 3)
        <div class="space-y-6">
            {{-- Success banner --}}
            <flux:callout color="green" icon="check-circle">
                <strong>Knowledge source created!</strong> You can now upload files or place them directly on the server.
            </flux:callout>

            @if (in_array($providerType, ['filesystem', 'yaml', 'json', 'markdown']))
                <livewire:admin.knowledge-sources.file-manager
                    :sourceId="$createdSourceId"
                    :sourceType="$providerType"
                    :sourceNamespace="$namespace"
                    :key="'file-manager-'.$createdSourceId"
                />
            @else
                <flux:card class="p-8 text-center">
                    <div class="rounded-full bg-zinc-100 dark:bg-zinc-800 p-4 mb-4 inline-block">
                        <flux:icon name="check-circle" class="size-10 text-green-500" />
                    </div>
                    <flux:heading size="lg" class="mb-2">All Set!</flux:heading>
                    <p class="text-zinc-500 dark:text-zinc-400 max-w-md mx-auto">
                        This knowledge source type doesn't require file uploads.
                        It will be ready for search indexing automatically.
                    </p>
                </flux:card>
            @endif

            <div class="flex justify-between">
                <flux:button variant="outline" wire:click="previousStep" icon="arrow-left">
                    Back
                </flux:button>
                <flux:button variant="primary" wire:click="finish" icon="check-circle">
                    Finish & View Source
                </flux:button>
            </div>
        </div>
    @endif
</div>
