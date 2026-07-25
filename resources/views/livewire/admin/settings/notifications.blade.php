<div>
    <form wire:submit="save" @change="$dispatch('settings-dirty')">
        <flux:heading size="lg" class="mb-4">Email Notifications</flux:heading>

        <flux:card class="mb-6">
            <div class="space-y-6">
                <flux:field>
                    <div class="flex items-center gap-3">
                        <flux:switch wire:model="emailNotificationsEnabled" />
                        <div>
                            <flux:label>Enable email notifications</flux:label>
                            <flux:description>Receive email alerts for important system events such as indexing failures and provider errors.</flux:description>
                        </div>
                    </div>
                </flux:field>

                @if ($emailNotificationsEnabled)
                <flux:field>
                    <flux:label>Notification Email Address</flux:label>
                    <flux:input type="email" wire:model="notificationEmail" placeholder="admin@example.com" />
                    <flux:error name="notificationEmail" />
                    <flux:description>Email address that will receive system notifications.</flux:description>
                </flux:field>
                @endif
            </div>
        </flux:card>

        <flux:heading size="lg" class="mb-4">Webhook Notifications</flux:heading>

        <flux:card class="mb-6">
            <div class="space-y-6">
                <flux:field>
                    <div class="flex items-center gap-3">
                        <flux:switch wire:model="webhookEnabled" />
                        <div>
                            <flux:label>Enable webhook notifications</flux:label>
                            <flux:description>Send event data as POST requests to an external webhook URL in real time.</flux:description>
                        </div>
                    </div>
                </flux:field>

                @if ($webhookEnabled)
                <flux:field>
                    <flux:label>Webhook URL</flux:label>
                    <flux:input type="url" wire:model="webhookUrl" placeholder="https://hooks.example.com/notifications" />
                    <flux:error name="webhookUrl" />
                    <flux:description>The endpoint that will receive POST requests with JSON payloads for each notification event.</flux:description>
                </flux:field>
                @endif
            </div>
        </flux:card>

        <flux:heading size="lg" class="mb-4">Event Triggers</flux:heading>

        <flux:card class="mb-6">
            <flux:description class="mb-4">Select which events should trigger notifications when they occur.</flux:description>

            <div class="space-y-4">
                <flux:field>
                    <div class="flex items-center gap-3">
                        <flux:checkbox wire:model="indexingCompletedNotify" />
                        <div>
                            <flux:label>Indexing completed</flux:label>
                            <flux:description>Notify when a document indexing batch finishes successfully.</flux:description>
                        </div>
                    </div>
                </flux:field>

                <flux:field>
                    <div class="flex items-center gap-3">
                        <flux:checkbox wire:model="indexingFailedNotify" />
                        <div>
                            <flux:label>Indexing failed</flux:label>
                            <flux:description>Notify when a document or batch fails to index, including the error details.</flux:description>
                        </div>
                    </div>
                </flux:field>
            </div>
        </flux:card>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">Save Settings</flux:button>
        </div>
    </form>
</div>
