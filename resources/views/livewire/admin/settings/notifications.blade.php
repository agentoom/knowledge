<div>
    <form wire:submit="save" @change="$dispatch('settings-dirty')">
        <flux:heading size="lg" class="mb-4">Email Notifications</flux:heading>

        <flux:card class="mb-6">
            <div class="space-y-6">
                <flux:field>
                    <div class="flex items-center gap-3">
                        <flux:switch wire:model.live="emailNotificationsEnabled" />
                        <div>
                            <flux:label>Enable email notifications</flux:label>
                            <flux:description>Receive email alerts for important system events such as indexing failures and provider errors.</flux:description>
                        </div>
                    </div>
                </flux:field>

                <div x-show="$wire.emailNotificationsEnabled" x-cloak>
                    <flux:field>
                        <flux:label>Notification Email Addresses</flux:label>
                        <flux:input type="text" wire:model="notificationEmail" placeholder="admin@example.com, ops@example.com" />
                        <flux:error name="notificationEmail" />
                        <flux:description>One or more email addresses, separated by commas. All addresses will receive system notifications.</flux:description>
                    </flux:field>
                </div>
            </div>
        </flux:card>

        <flux:heading size="lg" class="mb-4">Webhook Notifications</flux:heading>

        <flux:card class="mb-6">
            <div class="space-y-6">
                <flux:field>
                    <div class="flex items-center gap-3">
                        <flux:switch wire:model.live="webhookEnabled" />
                        <div>
                            <flux:label>Enable webhook notifications</flux:label>
                            <flux:description>Send event data as POST requests to an external webhook URL in real time.</flux:description>
                        </div>
                    </div>
                </flux:field>

                <div x-show="$wire.webhookEnabled" x-cloak>
                    <flux:field>
                        <flux:label>Webhook URL</flux:label>
                        <flux:input type="url" wire:model="webhookUrl" placeholder="https://hooks.slack.com/services/..." />
                        <flux:error name="webhookUrl" />
                        <flux:description>
                            The endpoint that will receive POST requests with JSON payloads for each notification event.
                        </flux:description>
                        <div class="mt-3 rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-900">
                            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-2">Example payload sent to this endpoint:</p>
                            <pre class="text-xs text-zinc-600 dark:text-zinc-300 overflow-x-auto">{
  "text": "Agentoom Alert: High search latency detected",
  "attachments": [{
    "title": "Search Performance",
    "fields": [
      { "title": "Query", "value": "how to refund" },
      { "title": "Latency", "value": "8234 ms" },
      { "title": "Providers", "value": "3" }
    ]
  }]
}</pre>
                        </div>
                    </flux:field>
                </div>
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

        <flux:heading size="lg" class="mb-4">Thresholds</flux:heading>

        <flux:card class="mb-6">
            <div class="space-y-6">
                <flux:field>
                    <flux:label>Search Latency Threshold (ms)</flux:label>
                    <flux:input type="number" wire:model="latencyThresholdMs" min="100" max="60000" />
                    <flux:error name="latencyThresholdMs" />
                    <flux:description>Alert when a search query takes longer than this threshold (in milliseconds).</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>Sync Failure Threshold</flux:label>
                    <flux:input type="number" wire:model="syncFailureThreshold" min="1" max="100" />
                    <flux:error name="syncFailureThreshold" />
                    <flux:description>Number of consecutive failures before triggering an alert.</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>Notification Cooldown (seconds)</flux:label>
                    <flux:input type="number" wire:model="cooldownSeconds" min="30" max="3600" />
                    <flux:error name="cooldownSeconds" />
                    <flux:description>Minimum time between duplicate alerts to prevent notification floods.</flux:description>
                </flux:field>
            </div>
        </flux:card>

        <flux:heading size="lg" class="mb-4">Alert Types</flux:heading>

        <flux:card class="mb-6">
            <flux:description class="mb-4">Choose which operational events trigger notifications.</flux:description>

            <div class="space-y-4">
                <flux:field>
                    <div class="flex items-center gap-3">
                        <flux:checkbox wire:model="searchLatencyAlerts" />
                        <div>
                            <flux:label>Search latency alerts</flux:label>
                            <flux:description>Notify when search queries exceed the latency threshold.</flux:description>
                        </div>
                    </div>
                </flux:field>

                <flux:field>
                    <div class="flex items-center gap-3">
                        <flux:checkbox wire:model="syncFailureAlerts" />
                        <div>
                            <flux:label>Sync failure alerts</flux:label>
                            <flux:description>Notify when a knowledge source or pipeline sync fails repeatedly.</flux:description>
                        </div>
                    </div>
                </flux:field>

                <flux:field>
                    <div class="flex items-center gap-3">
                        <flux:checkbox wire:model="federationFailureAlerts" />
                        <div>
                            <flux:label>Federation failure alerts</flux:label>
                            <flux:description>Notify when a remote federation server cannot be reached.</flux:description>
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
