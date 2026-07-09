<div>
    <flux:heading size="xl" class="mb-6">General Settings</flux:heading>

    <form wire:submit="save">
        <flux:card class="mb-6">
            <div class="space-y-6">
                <flux:field>
                    <flux:label>Application Name</flux:label>
                    <flux:input wire:model="appName" />
                    <flux:error name="appName" />
                    <flux:description>Displayed in the admin UI and MCP server.</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>Description</flux:label>
                    <flux:textarea wire:model="appDescription" rows="3" />
                    <flux:description>Brief description of this knowledge server instance.</flux:description>
                </flux:field>
            </div>
        </flux:card>

        <flux:heading size="lg" class="mb-4">Search Defaults</flux:heading>

        <flux:card class="mb-6">
            <div class="space-y-6">
                <flux:field>
                    <flux:label>Default Max Results</flux:label>
                    <flux:input type="number" wire:model="defaultMaxResults" min="1" max="100" />
                    <flux:error name="defaultMaxResults" />
                    <flux:description>Maximum number of results returned by search queries (1-100).</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>Default Planner Strategy</flux:label>
                    <flux:select wire:model="defaultPlannerStrategy">
                        @foreach ($availablePlannerStrategies as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </flux:select>
                    <flux:description>Determines how the Query Planner routes search requests.</flux:description>
                </flux:field>
            </div>
        </flux:card>

        <flux:heading size="lg" class="mb-4">Chunking</flux:heading>

        <flux:card class="mb-6">
            <div class="space-y-6">
                <flux:field>
                    <flux:label>Default Chunking Strategy</flux:label>
                    <flux:select wire:model="defaultChunkingStrategy">
                        @foreach ($availableChunkingStrategies as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </flux:select>
                    <flux:description>How documents are split into chunks for indexing.</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>Chunk Size</flux:label>
                    <flux:input type="number" wire:model="chunkSize" min="100" max="10000" />
                    <flux:error name="chunkSize" />
                    <flux:description>Target size per chunk in characters (100-10000).</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>Chunk Overlap</flux:label>
                    <flux:input type="number" wire:model="chunkOverlap" min="0" max="1000" />
                    <flux:error name="chunkOverlap" />
                    <flux:description>Number of overlapping characters between adjacent chunks (0-1000).</flux:description>
                </flux:field>
            </div>
        </flux:card>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary">Save Settings</flux:button>
        </div>
    </form>
</div>
