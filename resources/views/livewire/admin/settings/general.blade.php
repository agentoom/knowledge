<div>
    <form wire:submit="save" @change="$dispatch('settings-dirty')">
        <flux:heading size="lg" class="mb-4">Application</flux:heading>

        <flux:card class="mb-6">
            <div class="space-y-6">
                <flux:field>
                    <flux:label>Application Name</flux:label>
                    <flux:input wire:model="appName" />
                    <flux:error name="appName" />
                    <flux:description>Displayed in the admin UI header and the MCP server metadata.</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>Description</flux:label>
                    <flux:textarea wire:model="appDescription" rows="3" />
                    <flux:description>A short description of this knowledge server instance, visible to AI agents connecting via MCP.</flux:description>
                </flux:field>
            </div>
        </flux:card>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">Save Settings</flux:button>
        </div>
    </form>
</div>
