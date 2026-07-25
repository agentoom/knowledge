<div>
    <form wire:submit="save" @change="$dispatch('settings-dirty')">
        <flux:heading size="lg" class="mb-4">Vector Store Configuration</flux:heading>

        <flux:card class="mb-6">
            <div class="space-y-6">
                <flux:field>
                    <flux:label>Host</flux:label>
                    <flux:input wire:model="host" placeholder="typesense.example.com" />
                    <flux:error name="host" />
                    <flux:description>Hostname or IP address of your Typesense server (without protocol or port).</flux:description>
                </flux:field>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <flux:field>
                        <flux:label>Port</flux:label>
                        <flux:input wire:model="port" placeholder="8108" />
                        <flux:error name="port" />
                        <flux:description>Default Typesense port is 8108.</flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:label>Protocol</flux:label>
                        <flux:select wire:model="protocol">
                            <flux:select.option value="http">HTTP</flux:select.option>
                            <flux:select.option value="https">HTTPS</flux:select.option>
                        </flux:select>
                        <flux:error name="protocol" />
                        <flux:description>Use HTTPS for production servers.</flux:description>
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>API Key</flux:label>
                    <flux:input wire:model="apiKey" type="password" viewable placeholder="Your Typesense API key" />
                    <flux:error name="apiKey" />
                    <flux:description>Admin API key from your Typesense server.</flux:description>
                </flux:field>

                <flux:field>
                    <div class="flex items-center gap-3">
                        <flux:switch wire:model="isActive" />
                        <div>
                            <flux:label>Active</flux:label>
                            <flux:description>Enable this vector store for document indexing and search.</flux:description>
                        </div>
                    </div>
                </flux:field>
            </div>
        </flux:card>

        <flux:callout color="blue" class="mb-6">
            <flux:heading size="sm">How It Works</flux:heading>
            <flux:text>
                This application connects to Typesense for vector search and document indexing.
                Enter the connection details for your Typesense server — self-hosted or Typesense Cloud.
                After saving, verify the connection on the
                <flux:link href="{{ route('admin.health') }}" wire:navigate>Health dashboard</flux:link>.
            </flux:text>
        </flux:callout>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">Save Settings</flux:button>
        </div>
    </form>
</div>
