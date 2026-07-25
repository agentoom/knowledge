<div>
    <form wire:submit="save" @change="$dispatch('settings-dirty')">
        <flux:heading size="lg" class="mb-4">Storage Paths</flux:heading>

        <flux:card class="mb-6">
            <div class="space-y-6">
                <flux:field>
                    <flux:label>Knowledge Documents Path</flux:label>
                    <flux:input wire:model="knowledgePath" />
                    <flux:error name="knowledgePath" />
                    <flux:description>Directory where knowledge source documents are stored. Can be a mounted volume path (NFS, cloud bucket mount, etc.).</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>Processing Path</flux:label>
                    <flux:input wire:model="processingPath" />
                    <flux:error name="processingPath" />
                    <flux:description>Temporary directory for document processing (Apache Tika output, intermediate files).</flux:description>
                </flux:field>

                <flux:field>
                    <div class="flex items-center gap-3">
                        <flux:switch wire:model="isMountedVolume" />
                        <div>
                            <flux:label>Knowledge path is a mounted volume</flux:label>
                            <flux:description>Enable if the knowledge directory resides on an externally mounted filesystem.</flux:description>
                        </div>
                    </div>
                </flux:field>
            </div>
        </flux:card>

        <flux:callout color="amber" class="mb-6">
            <flux:heading size="sm">Restart Required</flux:heading>
            <flux:text>Changing storage paths may require restarting document pipeline workers to pick up the new configuration.</flux:text>
            <div class="mt-3">
                <flux:button type="button" variant="filled" color="amber" wire:click="restartWorkers" wire:loading.attr="disabled" wire:target="restartWorkers">
                    Restart Pipeline Workers
                </flux:button>
            </div>
        </flux:callout>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">Save Settings</flux:button>
        </div>
    </form>
</div>
