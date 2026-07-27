<div>
    <form wire:submit="save" @change="$dispatch('settings-dirty')">
        <flux:heading size="lg" class="mb-4">Federation Sync</flux:heading>

        <flux:card class="mb-6">
            <div class="space-y-6">
                <flux:field>
                    <flux:label>Sync Interval (minutes)</flux:label>
                    <flux:input type="number" wire:model="federationSyncInterval" min="1" max="1440" />
                    <flux:error name="federationSyncInterval" />
                    <flux:description>How often to refresh capabilities from remote federation servers.</flux:description>
                </flux:field>
            </div>
        </flux:card>

        <flux:heading size="lg" class="mb-4">Log Pruning</flux:heading>

        <flux:card class="mb-6">
            <div class="space-y-6">
                <flux:field>
                    <div class="flex items-center gap-3">
                        <flux:switch wire:model="logPruningEnabled" />
                        <div>
                            <flux:label>Enable application log pruning</flux:label>
                            <flux:description>Automatically delete old Laravel log files to conserve disk space.</flux:description>
                        </div>
                    </div>
                </flux:field>

                @if ($logPruningEnabled)
                <flux:field>
                    <flux:label>Log Retention (days)</flux:label>
                    <flux:input type="number" wire:model="logPruningAgeDays" min="1" max="365" />
                    <flux:error name="logPruningAgeDays" />
                    <flux:description>Log files older than this many days will be deleted.</flux:description>
                </flux:field>
                @endif
            </div>
        </flux:card>

        <flux:heading size="lg" class="mb-4">Retrieval Log Pruning</flux:heading>

        <flux:card class="mb-6">
            <div class="space-y-6">
                <flux:field>
                    <div class="flex items-center gap-3">
                        <flux:switch wire:model="retrievalLogPruningEnabled" />
                        <div>
                            <flux:label>Enable retrieval log pruning</flux:label>
                            <flux:description>Automatically delete old search history records from the database.</flux:description>
                        </div>
                    </div>
                </flux:field>

                @if ($retrievalLogPruningEnabled)
                <flux:field>
                    <flux:label>Retention (days)</flux:label>
                    <flux:input type="number" wire:model="retrievalLogPruningAgeDays" min="1" max="365" />
                    <flux:error name="retrievalLogPruningAgeDays" />
                    <flux:description>Search history records older than this many days will be deleted.</flux:description>
                </flux:field>
                @endif
            </div>
        </flux:card>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">Save Settings</flux:button>
        </div>
    </form>
</div>
