<div>
    <flux:heading size="xl" class="mb-6">Notification Settings</flux:heading>

    <form wire:submit="save">
        <flux:card class="mb-6">
            <div class="space-y-6">
                <flux:heading size="lg">Email Notifications</flux:heading>

                <flux:field>
                    <flux:switch wire:model="emailNotificationsEnabled" />
                    <flux:label>Enable email notifications</flux:label>
                    <flux:description>Send email alerts for important system events.</flux:description>
                </flux:field>

                @if ($emailNotificationsEnabled)
                <flux:field>
                    <flux:label>Notification Email Address</flux:label>
                    <flux:input type="email" wire:model="notificationEmail" />
                    <flux:error name="notificationEmail" />
                    <flux:description>Email address where notifications will be sent.</flux:description>
                </flux:field>
                @endif
            </div>
        </flux:card>

        <flux:card class="mb-6">
            <div class="space-y-6">
                <flux:heading size="lg">Webhook Notifications</flux:heading>

                <flux:field>
                    <flux:switch wire:model="webhookEnabled" />
                    <flux:label>Enable webhook notifications</flux:label>
                    <flux:description>POST event data to an external webhook URL.</flux:description>
                </flux:field>

                @if ($webhookEnabled)
                <flux:field>
                    <flux:label>Webhook URL</flux:label>
                    <flux:input type="url" wire:model="webhookUrl" />
                    <flux:error name="webhookUrl" />
                    <flux:description>URL that will receive POST requests for each notification event.</flux:description>
                </flux:field>
                @endif
            </div>
        </flux:card>

        <flux:card class="mb-6">
            <div class="space-y-6">
                <flux:heading size="lg">Event Triggers</flux:heading>
                <flux:description class="mb-4">Choose which events should trigger notifications.</flux:description>

                <flux:field>
                    <flux:checkbox wire:model="indexingCompletedNotify" />
                    <flux:label>Indexing completed</flux:label>
                    <flux:description>Notify when a document indexing batch finishes successfully.</flux:description>
                </flux:field>

                <flux:field>
                    <flux:checkbox wire:model="indexingFailedNotify" />
                    <flux:label>Indexing failed</flux:label>
                    <flux:description>Notify when a document or batch fails to index.</flux:description>
                </flux:field>
            </div>
        </flux:card>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary">Save Settings</flux:button>
        </div>
    </form>
</div>
