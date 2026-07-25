<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeKnowledgeProvider extends Command
{
    protected $signature = 'make:knowledge-provider
                            {name : The name of the provider class (e.g., Salesforce, Jira)}
                            {--namespace= : The provider namespace key (default: kebab-case of name)}';

    protected $description = 'Scaffold a new Knowledge Provider with boilerplate implementation';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $namespace = $this->option('namespace') ?? Str::kebab($name);
        $className = $name.'Provider';
        $path = app_path("Providers/{$className}.php");

        if (File::exists($path)) {
            $this->error("Provider [{$className}] already exists at {$path}.");

            return self::FAILURE;
        }

        $stub = $this->generateStub($className, $namespace);

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $stub);

        $this->info("Provider [{$className}] created at {$path}.");
        $this->newLine();
        $this->line('Next steps:');
        $this->line("  1. Implement the search() method in {$className}");
        $this->line('  2. Register provider type in App\\Observers\\KnowledgeSourceObserver::resolveProviderClass()');
        $this->line('  3. Add capabilities in KnowledgeSourceObserver::resolveCapabilities()');
        $this->line('  4. Optionally add a discoverFiles() method for document pipeline support');
        $this->newLine();
        $this->line('See docs/extending.md for full instructions.');

        return self::SUCCESS;
    }

    private function generateStub(string $className, string $namespace): string
    {
        $namespaceKeyword = var_export($namespace, true);

        return <<<PHP
<?php

namespace App\Providers;

use App\Contracts\KnowledgeProvider;
use App\Knowledge\Models\ProviderMetadata;
use App\Retrieval\Models\SearchQuery;
use App\Retrieval\Models\SearchResult;
use Illuminate\Support\Collection;

class {$className} implements KnowledgeProvider
{
    public function __construct(
        // Add your configuration parameters here.
        // These will be injected from the provider_config column.
    ) {}

    public function metadata(): ProviderMetadata
    {
        return new ProviderMetadata(
            capabilities: ['search', 'list_resources'],
            searchableResources: ['{$namespace}_resources'],
            searchableFields: ['title', 'content'],
            namespace: {$namespaceKeyword},
            supportedOperations: ['full_text'],
        );
    }

    public function search(SearchQuery \$query): SearchResult
    {
        // TODO: Implement your search logic here.
        // Access \$query->query, \$query->maxResults, \$query->filters, \$query->namespace

        return new SearchResult(
            items: [],
            totalCount: 0,
            providerName: '{$namespace}',
        );
    }

    public function supports(string \$operation): bool
    {
        return in_array(\$operation, \$this->metadata()->supportedOperations, true);
    }

    /**
     * Optional: return discoverable files for document pipeline ingestion.
     *
     * @return Collection<int, array{path: string, filename: string, size: int}>
     */
    public function discoverFiles(): Collection
    {
        return collect();
    }
}
PHP;
    }
}
