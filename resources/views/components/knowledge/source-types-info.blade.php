<div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 overflow-hidden">
    <div class="px-6 py-4 bg-zinc-50 dark:bg-zinc-800/50 border-b border-zinc-200 dark:border-zinc-700">
        <div class="flex items-center gap-2">
            <flux:icon name="light-bulb" class="size-5 text-amber-500" />
            <flux:heading size="lg">How source types affect retrieval</flux:heading>
        </div>
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
            Each source type has a specialized provider that reads its native format and returns structured results differently at search time.
        </p>
    </div>
    <div class="px-6 py-4 space-y-4 text-sm text-zinc-600 dark:text-zinc-400">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach ([
                ['📄', 'Filesystem', 'Generic full-text across all file formats. Best for mixed document repositories.', 'Uses Tika for parsing — supports docs, PDFs, spreadsheets, images (metadata), ebooks, email, archives.'],
                ['📝', 'Markdown', 'Heading-aware parsing and semantic chunking. Best for documentation, guides, knowledge bases, and agent skills.', 'Chunks on heading boundaries for context-preserving retrieval. Each heading section becomes an independently searchable chunk.'],
                ['📋', 'YAML', 'Searches key-value pairs individually — each entry is a separate search hit.', 'Returns <code class="text-xs bg-zinc-100 dark:bg-zinc-800 px-1 py-0.5 rounded">key → value</code> results per entry. Best for configs, translations, data catalogs.'],
                ['{ }', 'JSON', 'Decodes nested structures and searches within values.', 'Preserves hierarchy — nested objects are searchable. Best for API responses and structured records.'],
                ['🗄️', 'SQL Database', 'Connects to external databases and returns row-level results.', 'Supports MySQL, PostgreSQL, SQLite, SQL Server. Configurable via named connection or inline credentials.'],
                ['🌐', 'Web Crawler', 'Crawls and converts HTML to structured markdown.', 'Recursive crawling with configurable depth. Best for external documentation sites.'],
            ] as [$icon, $name, $purpose, $detail])
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                    <p class="font-medium text-zinc-800 dark:text-zinc-200 mb-1">
                        {{ $icon }} {{ $name }}
                    </p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-1.5">{{ $purpose }}</p>
                    <p class="text-xs text-zinc-400 dark:text-zinc-500 leading-relaxed">{!! $detail !!}</p>
                </div>
            @endforeach
        </div>

        <div class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/30 p-3">
            <p class="text-xs font-medium text-amber-800 dark:text-amber-200 mb-1">💡 Multiple sources, same namespace</p>
            <p class="text-xs text-amber-700 dark:text-amber-300">
                Need to search both YAML configs and Markdown docs with a single query?
                Create separate sources with the <strong>same namespace</strong>.
                The retrieval engine federates across all providers in that namespace and merges results with Reciprocal Rank Fusion.
            </p>
        </div>

        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 p-3">
            <p class="text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1">📦 Filesystem vs. type-specific sources</p>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                All filesystem-backed sources go through the same indexing pipeline (Tika → chunk → Typesense).
                The type-specific sources (YAML, JSON, Markdown) differ only at the <strong>provider search level</strong>:
                their providers understand the native format and return richer, structured results.
                If you only use the Playground, a single Filesystem source with multiple namespaces is sufficient.
            </p>
        </div>
    </div>
</div>
