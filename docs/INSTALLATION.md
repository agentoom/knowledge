# Installation Guide

This guide covers setting up Agentoom Knowledge from scratch, both for development and production.

## Prerequisites

- [Docker](https://docs.docker.com/get-docker/) and Docker Compose
- [Git](https://git-scm.com/)
- PHP 8.3+, [Composer](https://getcomposer.org/), and [Node.js](https://nodejs.org/) (for running commands outside containers)

## Step 1 — Clone the Repository

```bash
git clone https://github.com/agentoom/knowledge.git
cd knowledge
```

## Step 2 — Configure the Environment

```bash
cp .env.example .env
```

Edit `.env` and set your database password, Typesense API key, and domain URL. Defaults work for Docker out of the box — only `DB_PASSWORD` and `TYPESENSE_API_KEY` are required.

## Step 3 — Start the Application

```bash
docker compose -f compose.prod.yaml up -d
```

The entrypoint handles everything automatically:
- Waits for PostgreSQL, Redis, Typesense, Tika
- Runs migrations
- Seeds demo knowledge sources, documents, and chunks
- Indexes chunks into Typesense
- Builds the metadata registry
- Caches routes and config for production

That's it. Open `http://your-domain.com` and log in with:

| Email | Password |
|-------|----------|
| `knowledge@agentoom.com` | `changeme` |

> ⚠️ **Change the password immediately** after logging in. Navigate to **Search Playground** and search for anything — results from all 4 providers are ready.

## Optional: Manual Setup

If you need to run steps individually or reset demo data:

```bash
# Install dependencies and build assets
composer install --no-dev --optimize-autoloader
npm install && npm run build

# Generate app key
php artisan key:generate

# Run migrations and demo seeder
php artisan migrate --force
composer knowledge:demo

# Create admin user (idempotent)
php artisan knowledge:install
```

## Useful Artisan Commands

| Command | Description |
|---------|-------------|
| `knowledge:install` | Create the default superadmin user |
| `knowledge:pipeline:run` | Run the document pipeline for all active sources |
| `knowledge:chunks:index` | Dispatch IndexChunk jobs for all unindexed chunks |
| `knowledge:providers:list` | List all registered knowledge providers |
| `knowledge:providers:sync` | Sync provider metadata and status from sources |
| `knowledge:registry:refresh` | Rebuild the metadata registry |
| `app:federation-sync` | Manually sync capabilities from remote federation servers |
| `make:knowledge-provider` | Scaffold a new custom Knowledge Provider |

## Running Tests

```bash
php artisan test --compact
```

The `.env.testing` file provides CI-friendly defaults (SQLite in-memory, array cache, sync queue). Tests that exercise Typesense need Docker services running.

## Troubleshooting

### Health Check

Verify all critical services are running:

```bash
curl http://localhost/health
```

Returns JSON with the status of database, Redis, Typesense, and storage. HTTP 200 = all healthy, HTTP 503 = at least one service is down.

### "Unsupported cipher or incorrect key length"

Run `php artisan key:generate` — the `APP_KEY` in your `.env` is missing or invalid.

### Search returns zero results

1. Ensure **queue workers are running** and processing `IndexChunk` jobs
2. Run `php artisan knowledge:chunks:index` to dispatch indexing jobs for existing chunks
3. Rebuild the **metadata registry**: `php artisan knowledge:registry:refresh`
4. Check Typesense is responding: `curl http://localhost:8108/health`

### Documents stuck in "discovered" status

Click **Sync Now** on the knowledge source detail page, or run:

```bash
php artisan tinker --execute "
\$source = App\Knowledge\Models\KnowledgeSource::find(1);
app(App\DocumentPipeline\Services\PipelineOrchestrator::class)->run(\$source);
"
```

### Connection refused to Typesense or Tika

Docker services may not be running. Check with `docker compose ps` and restart with `docker compose up -d`.
