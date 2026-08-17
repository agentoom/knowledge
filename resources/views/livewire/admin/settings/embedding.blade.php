<div>
    <form wire:submit="save" @change="$dispatch('settings-dirty')">
        <flux:heading size="lg" class="mb-4">Embedding Provider</flux:heading>

        <flux:card class="mb-6">
            <div class="space-y-6">
                <flux:field>
                    <flux:label>Provider</flux:label>
                    <flux:select wire:model="provider" wire:change="changedProvider">
                        @foreach ($availableProviders as $value => $label)
                            <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="provider" />
                    <flux:description>
                        <strong>Typesense (Managed)</strong> keeps the current behavior: the vector store computes embeddings
                        internally from document text. OpenAI, Cohere, and HuggingFace compute vectors client-side through the
                        document pipeline and hybrid search.
                    </flux:description>
                </flux:field>

                @if ($provider !== 'typesense')
                    <flux:field>
                        <flux:label>Model</flux:label>
                        <flux:input wire:model="model" placeholder="text-embedding-3-small" />
                        <flux:error name="model" />
                        <flux:description>The embedding model identifier sent with each request.</flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:label>Endpoint</flux:label>
                        <flux:input wire:model="endpoint" placeholder="https://api.openai.com/v1" />
                        <flux:error name="endpoint" />
                        <flux:description>
                            Base URL of the embedding API. For HuggingFace, point this at your local
                            inference endpoint (e.g. <code>http://tei:8080/embed</code>).
                        </flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:label>Vector Dimensions</flux:label>
                        <flux:input type="number" wire:model="dimensions" min="1" max="100000" />
                        <flux:error name="dimensions" />
                        <flux:description>Expected dimensionality of the model's output vectors. Must match your existing index when reindexing.</flux:description>
                    </flux:field>
                @endif

                <flux:callout color="blue">
                    <flux:heading size="sm">API Keys</flux:heading>
                    <flux:text>
                        Provider API secrets are read from environment variables (<code>OPENAI_API_KEY</code>,
                        <code>COHERE_API_KEY</code>, <code>HUGGINGFACE_API_TOKEN</code>) and are never stored in the
                        database. Only the provider selection and non-secret connection values are persisted here.
                    </flux:text>
                </flux:callout>
            </div>
        </flux:card>

        <flux:card class="mb-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <flux:heading size="sm">Connection Test</flux:heading>
                    <flux:text>
                        Sends a probe embedding request to verify the provider is reachable and returns the expected dimensions.
                    </flux:text>
                </div>
                <flux:button type="button" variant="outline" wire:click="testConnection" wire:loading.attr="disabled" wire:target="testConnection">
                    {{ $testing ? 'Testing…' : 'Test Connection' }}
                </flux:button>
            </div>

            @if ($connectionStatus !== '')
                <flux:separator class="my-4" />
                <flux:callout :color="str_starts_with($connectionStatus, 'Success') || str_starts_with($connectionStatus, 'Managed') ? 'green' : 'red'">
                    <flux:text>{{ $connectionStatus }}</flux:text>
                </flux:callout>
            @endif
        </flux:card>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">Save Settings</flux:button>
        </div>
    </form>
</div>
