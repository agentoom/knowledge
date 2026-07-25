<div>
    <flux:heading size="xl" class="mb-6">System Health</flux:heading>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach ($checks as $name => $check)
            <flux:card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading size="lg" class="capitalize">{{ str_replace('_', ' ', $name) }}</flux:heading>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">{{ $check['message'] }}</p>
                    </div>
                    <flux:badge
                        :color="match($check['status']) {
                            'ok' => 'green',
                            'warning' => 'yellow',
                            'error' => 'red',
                            default => 'gray'
                        }"
                        size="lg"
                    >
                        {{ $check['status'] }}
                    </flux:badge>
                </div>
            </flux:card>
        @endforeach
    </div>

    <div class="mt-8">
        <flux:card>
            <flux:heading size="lg">Environment</flux:heading>
            <dl class="mt-4 grid grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">Laravel</dt>
                    <dd class="font-mono">{{ app()->version() }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">PHP</dt>
                    <dd class="font-mono">{{ phpversion() }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">Environment</dt>
                    <dd class="font-mono">{{ app()->environment() }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">Queue</dt>
                    <dd class="font-mono">{{ config('queue.default') }}</dd>
                </div>
            </dl>
        </flux:card>
    </div>
</div>
