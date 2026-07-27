<div>
    <form wire:submit="save" @change="$dispatch('settings-dirty')">
        <flux:heading size="lg" class="mb-4">MCP API Rate Limiting</flux:heading>

        <flux:card class="mb-6">
            <flux:description class="mb-4">
                Rate limiting protects the MCP API endpoint from abuse by capping the number of requests each API key can make per minute.
                When a key exceeds the limit, subsequent requests receive a <code>429 Too Many Requests</code> response until the window resets.
            </flux:description>

            <div class="space-y-6">
                <flux:field>
                    <div class="flex items-center gap-3">
                        <flux:switch wire:model="rateLimitingEnabled" />
                        <div>
                            <flux:label>Enable rate limiting</flux:label>
                            <flux:description>When disabled, all MCP API requests are allowed without throttling.</flux:description>
                        </div>
                    </div>
                </flux:field>

                @if ($rateLimitingEnabled)
                <flux:field>
                    <flux:label>Max Requests Per Minute (per API key)</flux:label>
                    <flux:input type="number" wire:model="rateLimitPerMinute" min="1" max="10000" />
                    <flux:error name="rateLimitPerMinute" />
                    <flux:description>Each API key can make up to this many requests within a one-minute sliding window.</flux:description>
                </flux:field>
                @endif
            </div>
        </flux:card>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">Save Settings</flux:button>
        </div>
    </form>
</div>
