<?php

namespace App\Providers\Sql;

use App\Contracts\KnowledgeProvider;
use App\Knowledge\Models\ProviderMetadata;
use App\Retrieval\Models\SearchQuery;
use App\Retrieval\Models\SearchResult;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class SqlProvider implements KnowledgeProvider
{
    private string $connection;

    private string $table;

    /** @var array<int, string> */
    private array $searchableColumns;

    public function __construct(string|array $connection, string $table, ?array $searchableColumns = null)
    {
        if (is_array($connection)) {
            // Decrypt password if it was stored encrypted
            if (isset($connection['password']) && is_string($connection['password']) && $connection['password'] !== '') {
                try {
                    $connection['password'] = Crypt::decryptString($connection['password']);
                } catch (\Throwable) {
                    // Password was not encrypted; use as-is
                }
            }

            $connectionName = 'dynamic_sql_'.md5(json_encode($connection));
            config(["database.connections.{$connectionName}" => $connection]);
            $this->connection = $connectionName;
        } else {
            $this->connection = $connection;
        }

        $this->table = $table;
        $this->searchableColumns = $searchableColumns ?? ['*'];
    }

    public function metadata(): ProviderMetadata
    {
        return new ProviderMetadata(
            capabilities: ['search', 'schema_query'],
            searchableResources: [$this->table],
            searchableFields: $this->searchableColumns,
            namespace: 'sql',
            supportedOperations: ['full_text', 'structured_filter'],
        );
    }

    public function search(SearchQuery $query): SearchResult
    {
        $connection = DB::connection($this->connection);
        $dbQuery = $connection->table($this->table);
        $likeOperator = $connection->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $allowedColumns = $this->getColumns();

        if (! empty($query->filters)) {
            foreach ($query->filters as $column => $value) {
                if (in_array($column, $allowedColumns, true)) {
                    $dbQuery->where($column, $value);
                }
            }
        }

        if (! empty($query->query)) {
            $dbQuery->where(function ($q) use ($query, $likeOperator, $allowedColumns) {
                foreach ($allowedColumns as $column) {
                    $q->orWhere($column, $likeOperator, "%{$query->query}%");
                }
            });
        }

        $results = $dbQuery->limit($query->maxResults)->get()->toArray();

        return new SearchResult(
            items: $results,
            totalCount: count($results),
            providerName: 'sql',
            metadata: ['table' => $this->table, 'connection' => $this->connection],
        );
    }

    public function supports(string $operation): bool
    {
        return in_array($operation, $this->metadata()->supportedOperations);
    }

    /**
     * @return array<int, string>
     */
    public function getColumns(): array
    {
        if ($this->searchableColumns === ['*']) {
            return DB::connection($this->connection)
                ->getSchemaBuilder()
                ->getColumnListing($this->table);
        }

        return $this->searchableColumns;
    }
}
