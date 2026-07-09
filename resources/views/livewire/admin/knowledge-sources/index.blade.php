<div>
    <div class="flex justify-between items-center mb-6">
        <flux:heading size="xl">Knowledge Sources</flux:heading>
        <flux:button icon="plus" wire:click="$set('showCreateModal', true)">
            Add Source
        </flux:button>
    </div>

    @if (session()->has('status'))
        <flux:callout color="green" class="mb-4">{{ session('status') }}</flux:callout>
    @endif

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
                <flux:table.cell>{{ $source->provider_type }}</flux:table.cell>
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

    <flux:modal wire:model="showCreateModal" class="max-w-lg">
        <form wire:submit="create">
            <flux:heading size="lg">Add Knowledge Source</flux:heading>

            <div class="mt-4 space-y-4">
                <flux:field>
                    <flux:label>Name</flux:label>
                    <flux:input wire:model="name" placeholder="My Documentation" />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>Namespace</flux:label>
                    <flux:input wire:model="namespace" placeholder="docs" />
                    <flux:error name="namespace" />
                </flux:field>

                <flux:field>
                    <flux:label>Provider Type</flux:label>
                    <flux:select wire:model.live="providerType">
                        <flux:select.option value="filesystem">Filesystem</flux:select.option>
                        <flux:select.option value="sql">SQL Database</flux:select.option>
                        <flux:select.option value="yaml">YAML Files</flux:select.option>
                        <flux:select.option value="json">JSON Files</flux:select.option>
                        <flux:select.option value="web">Web Crawler</flux:select.option>
                    </flux:select>
                    <flux:error name="providerType" />
                </flux:field>

                {{-- SQL Provider Config --}}
                @if ($providerType === 'sql')
                    <flux:separator />

                    <flux:field>
                        <flux:label>SQL Connection</flux:label>
                        <div class="flex items-center gap-2 mt-1">
                            <flux:checkbox wire:model.live="configUseDynamicConnection" label="Use custom credentials" />
                        </div>
                        <flux:description class="mt-1">
                            Enable to enter connection details directly. Otherwise, use a named connection from <code>config/database.php</code>.
                        </flux:description>
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
                                <flux:error name="configDriver" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Port</flux:label>
                                <flux:input wire:model="configPort" placeholder="3306" />
                                <flux:error name="configPort" />
                            </flux:field>
                        </div>

                        <flux:field>
                            <flux:label>Host</flux:label>
                            <flux:input wire:model="configHost" placeholder="db.example.com" />
                            <flux:error name="configHost" />
                        </flux:field>

                        <div class="grid grid-cols-2 gap-4">
                            <flux:field>
                                <flux:label>Database</flux:label>
                                <flux:input wire:model="configDatabase" placeholder="my_database" />
                                <flux:error name="configDatabase" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Username</flux:label>
                                <flux:input wire:model="configUsername" placeholder="root" />
                                <flux:error name="configUsername" />
                            </flux:field>
                        </div>

                        <flux:field>
                            <flux:label>Password</flux:label>
                            <flux:input type="password" wire:model="configPassword" placeholder="••••••" />
                            <flux:error name="configPassword" />
                        </flux:field>
                    @else
                        <flux:field>
                            <flux:label>Connection Name</flux:label>
                            <flux:input wire:model="configConnectionName" placeholder="pgsql" />
                            <flux:description class="mt-1">Named connection from config/database.php</flux:description>
                            <flux:error name="configConnectionName" />
                        </flux:field>
                    @endif

                    <flux:field>
                        <flux:label>Table</flux:label>
                        <flux:input wire:model="configTable" placeholder="users" />
                        <flux:description class="mt-1">The database table to search</flux:description>
                        <flux:error name="configTable" />
                    </flux:field>
                @endif

                {{-- Filesystem / YAML / JSON Provider Config --}}
                @if (in_array($providerType, ['filesystem', 'yaml', 'json']))
                    <flux:separator />

                    <flux:field>
                        <flux:label>Base Path</flux:label>
                        <flux:input wire:model="configBasePath" placeholder="/var/www/storage/app/knowledge/docs" />
                        <flux:description class="mt-1">Absolute path to the directory containing {{ $providerType }} files</flux:description>
                        <flux:error name="configBasePath" />
                    </flux:field>
                @endif

                {{-- Web Provider Config --}}
                @if ($providerType === 'web')
                    <flux:separator />

                    <flux:field>
                        <flux:label>URLs</flux:label>
                        <flux:textarea wire:model="configUrls" rows="4" placeholder="https://docs.example.com&#10;https://api.example.com/reference" />
                        <flux:description class="mt-1">One URL per line to crawl for documentation</flux:description>
                        <flux:error name="configUrls" />
                    </flux:field>
                @endif
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <flux:button variant="outline" wire:click="$set('showCreateModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Create</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showEditModal" class="max-w-lg">
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
                        <flux:select.option value="filesystem">Filesystem</flux:select.option>
                        <flux:select.option value="sql">SQL Database</flux:select.option>
                        <flux:select.option value="yaml">YAML Files</flux:select.option>
                        <flux:select.option value="json">JSON Files</flux:select.option>
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
                        <flux:description class="mt-1">
                            Enable to enter connection details directly. Otherwise, use a named connection from <code>config/database.php</code>.
                        </flux:description>
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
                                <flux:error name="editConfigDriver" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Port</flux:label>
                                <flux:input wire:model="editConfigPort" placeholder="3306" />
                                <flux:error name="editConfigPort" />
                            </flux:field>
                        </div>

                        <flux:field>
                            <flux:label>Host</flux:label>
                            <flux:input wire:model="editConfigHost" placeholder="db.example.com" />
                            <flux:error name="editConfigHost" />
                        </flux:field>

                        <div class="grid grid-cols-2 gap-4">
                            <flux:field>
                                <flux:label>Database</flux:label>
                                <flux:input wire:model="editConfigDatabase" placeholder="my_database" />
                                <flux:error name="editConfigDatabase" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Username</flux:label>
                                <flux:input wire:model="editConfigUsername" placeholder="root" />
                                <flux:error name="editConfigUsername" />
                            </flux:field>
                        </div>

                        <flux:field>
                            <flux:label>Password</flux:label>
                            <flux:input type="password" wire:model="editConfigPassword" placeholder="••••••" />
                            <flux:error name="editConfigPassword" />
                        </flux:field>
                    @else
                        <flux:field>
                            <flux:label>Connection Name</flux:label>
                            <flux:input wire:model="editConfigConnectionName" placeholder="pgsql" />
                            <flux:description class="mt-1">Named connection from config/database.php</flux:description>
                            <flux:error name="editConfigConnectionName" />
                        </flux:field>
                    @endif

                    <flux:field>
                        <flux:label>Table</flux:label>
                        <flux:input wire:model="editConfigTable" placeholder="users" />
                        <flux:description class="mt-1">The database table to search</flux:description>
                        <flux:error name="editConfigTable" />
                    </flux:field>
                @endif

                {{-- Filesystem / YAML / JSON Provider Config --}}
                @if (in_array($editProviderType, ['filesystem', 'yaml', 'json']))
                    <flux:separator />

                    <flux:field>
                        <flux:label>Base Path</flux:label>
                        <flux:input wire:model="editConfigBasePath" placeholder="/var/www/storage/app/knowledge/docs" />
                        <flux:description class="mt-1">Absolute path to the directory containing {{ $editProviderType }} files</flux:description>
                        <flux:error name="editConfigBasePath" />
                    </flux:field>
                @endif

                {{-- Web Provider Config --}}
                @if ($editProviderType === 'web')
                    <flux:separator />

                    <flux:field>
                        <flux:label>URLs</flux:label>
                        <flux:textarea wire:model="editConfigUrls" rows="4" placeholder="https://docs.example.com&#10;https://api.example.com/reference" />
                        <flux:description class="mt-1">One URL per line to crawl for documentation</flux:description>
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
