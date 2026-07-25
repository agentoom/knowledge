<div>
    <div class="mb-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="rounded-full bg-red-100 dark:bg-red-950 p-2">
                <flux:icon name="exclamation-triangle" class="size-6 text-red-600 dark:text-red-400" />
            </div>
            <flux:heading size="lg" class="text-red-600 dark:text-red-400">Danger Zone</flux:heading>
        </div>

        <p class="text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
            The actions in this section are <strong class="text-red-600 dark:text-red-400">irreversible</strong>.
            They will permanently delete data from both the database and the search index.
            Make sure you have a backup before proceeding.
        </p>
    </div>

    {{-- Error / Success messages --}}
    @if ($resultError)
        <flux:callout color="red" icon="exclamation-circle" class="mb-6">{{ $resultError }}</flux:callout>
    @endif

    @if ($resultMessage)
        <flux:callout color="green" icon="check-circle" class="mb-6">{{ $resultMessage }}</flux:callout>
    @endif

    {{-- Reset App card --}}
    <flux:card class="border-red-300 dark:border-red-800 bg-red-50/50 dark:bg-red-950/20">
        <div class="space-y-4">
            <div>
                <flux:heading size="sm" class="text-red-700 dark:text-red-300">Reset Application</flux:heading>
                <div class="mt-3 space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
                    <p>
                        This will <strong>permanently delete</strong> all knowledge-related data including:
                    </p>
                    <ul class="list-disc pl-5 space-y-1 text-zinc-600 dark:text-zinc-400">
                        <li>All <strong>Knowledge Sources</strong> and their configurations (SQL connections, web crawler URLs, etc.)</li>
                        <li>All <strong>Documents</strong> and their parsed content</li>
                        <li>All <strong>Chunks</strong> generated from documents</li>
                        <li>All <strong>indexed data</strong> from the search engine (Typesense)</li>
                        <li>All <strong>retrieval logs</strong> and search history</li>
                        <li>All <strong>metadata registry</strong> entries</li>
                        <li>All <strong>pipeline job history</strong> and queued jobs</li>
                        <li>All <strong>activity logs</strong></li>
                    </ul>
                </div>
                <p class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">
                    This will <strong>not</strong> affect your user accounts, API keys, settings, or server configuration.
                </p>
            </div>

            <flux:separator />

            <div>
                <flux:button
                    variant="danger"
                    icon="exclamation-triangle"
                    wire:click="confirmReset"
                    :disabled="$resetting"
                >
                    {{ $resetting ? 'Resetting...' : 'Reset Application' }}
                </flux:button>

                @if ($resetting)
                    <div class="flex items-center gap-2 mt-3 text-sm text-zinc-500">
                        <flux:icon name="arrow-path" class="size-4 animate-spin" />
                        Resetting application data...
                    </div>
                @endif
            </div>
        </div>
    </flux:card>

    {{-- Confirmation Modal --}}
    <flux:modal wire:model="showConfirmModal" class="max-w-md">
        <div class="space-y-5">
            <div class="flex items-center gap-3">
                <div class="rounded-full bg-red-100 dark:bg-red-950 p-2 shrink-0">
                    <flux:icon name="exclamation-triangle" class="size-6 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    <flux:heading size="lg" class="text-red-600 dark:text-red-400">Confirm Reset</flux:heading>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">
                        This action is <strong class="text-red-600 dark:text-red-400">irreversible</strong>
                    </p>
                </div>
            </div>

            <div class="rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/30 p-4">
                <p class="text-sm text-zinc-700 dark:text-zinc-300">
                    You are about to permanently delete <strong>all</strong> knowledge sources, documents, chunks,
                    indexed data, retrieval logs, pipeline jobs, and activity logs.
                </p>
                <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-2">
                    This cannot be undone. Make sure you have exported any data you want to keep.
                </p>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="outline" wire:click="cancelReset">
                    Cancel
                </flux:button>
                <flux:button variant="danger" wire:click="resetApp">
                    Yes, Reset Everything
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
