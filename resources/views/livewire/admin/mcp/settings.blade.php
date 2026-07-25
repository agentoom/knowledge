<div>
    <flux:heading size="xl" class="mb-6">MCP Settings</flux:heading>

    <flux:card class="mb-4">
        <flux:heading size="lg" class="mb-3">Server Information</flux:heading>
        <div class="space-y-2">
            @foreach ($serverInfo as $key => $value)
                <div class="flex gap-2">
                    <span class="font-semibold text-sm">{{ $key }}:</span>
                    <span class="text-sm text-gray-600">{{ $value }}</span>
                </div>
            @endforeach
        </div>
    </flux:card>

    <flux:card class="mb-4">
        <flux:heading size="lg" class="mb-3">Tools ({{ count($tools) }})</flux:heading>
        <div class="space-y-2">
            @foreach ($tools as $tool)
                <div class="flex items-center gap-2">
                    <flux:badge color="blue">{{ $tool['name'] }}</flux:badge>
                    <span class="text-sm text-gray-600">{{ $tool['description'] }}</span>
                </div>
            @endforeach
        </div>
    </flux:card>

    <flux:card class="mb-4">
        <flux:heading size="lg" class="mb-3">Prompts ({{ count($prompts) }})</flux:heading>
        <div class="space-y-2">
            @foreach ($prompts as $prompt)
                <div class="flex items-center gap-2">
                    <flux:badge color="purple">{{ $prompt['name'] }}</flux:badge>
                    <span class="text-sm text-gray-600">{{ $prompt['description'] }}</span>
                </div>
            @endforeach
        </div>
    </flux:card>

    <flux:card>
        <flux:heading size="lg" class="mb-3">API Keys</flux:heading>
        <p class="text-sm text-gray-500 mb-3">Manage API keys for MCP access.</p>
        <flux:button wire:navigate href="{{ route('admin.mcp.api-keys') }}">
            Manage API Keys
        </flux:button>
    </flux:card>
</div>
