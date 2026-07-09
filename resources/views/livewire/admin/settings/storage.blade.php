<div>
    <flux:heading size="xl" class="mb-6">Storage Settings</flux:heading>

    <form wire:submit="save">
        <flux:card class="mb-6">
            <div class="space-y-6">
                <flux:field>
                    <flux:label>Knowledge Documents Path</flux:label>
                    <flux:input wire:model="knowledgePath" />
                    <flux:error name="knowledgePath" />
                    <flux:description>Directory where knowledge source documents are stored. Can be a mounted volume path.</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>Processing Path</flux:label>
                    <flux:input wire:model="processingPath" />
                    <flux:error name="processingPath" />
                    <flux:description>Temporary directory for document processing (Tika output, intermediate files).</flux:description>
                </flux:field>

                <flux:field>
                    <flux:switch wire:model="isMountedVolume" />
                    <flux:label>Knowledge path is a mounted volume</flux:label>
                    <flux:description>Enable if the knowledge directory is a mounted external volume (NFS, cloud bucket mount).</flux:description>
                </flux:field>
            </div>
        </flux:card>

        <flux:callout color="amber" class="mb-6">
            <flux:heading size="sm">Restart Required</flux:heading>
            <flux:text>Changing storage paths may require restarting the document pipeline workers to pick up the new configuration.</flux:text>
        </flux:callout>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary">Save Settings</flux:button>
        </div>
    </form>
</div>
