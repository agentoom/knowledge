# How to Use Agentoom Knowledge

This guide walks you through the core workflows. You should have completed the [installation](INSTALLATION.md) first.

## Table of Contents

- [Logging In](#logging-in)
- [Dashboard Overview](#dashboard-overview)
- [Adding Your First Knowledge Source](#adding-your-first-knowledge-source)
  - [Filesystem](#filesystem)
  - [Markdown Files](#markdown-files)
  - [SQL Database](#sql-database)
  - [Web Crawler](#web-crawler)
  - [YAML / JSON Files](#yaml--json-files)
- [Syncing and Indexing](#syncing-and-indexing)
- [Searching Your Knowledge](#searching-your-knowledge)
- [Managing Sources](#managing-sources)
- [MCP: Connecting AI Agents](#mcp-connecting-ai-agents)
- [Federation](#federation)
- [Users and Roles](#users-and-roles)
- [Settings](#settings)
- [Troubleshooting](#troubleshooting)

---

## Logging In

Open your application URL and log in with your admin credentials.

The admin user is created during seeding. If you didn't set `ADMIN_EMAIL` / `ADMIN_PASSWORD` in your `.env`, the password was randomly generated and printed to the console during seeding (also saved to `storage/app/initial-admin-password.txt`).

> Change the default password immediately: click your avatar → **Profile** → update password.

If you enabled two-factor authentication, you'll be prompted for your TOTP code or passkey.

---

## Dashboard Overview

The dashboard shows:

- **Knowledge Sources** — how many sources you have configured
- **Documents** — total documents discovered across all sources
- **Providers** — active retrieval providers
- **Queue Health** — status of Horizon workers (if running)

Use the sidebar to navigate between sections.

---

## Adding Your First Knowledge Source

Click **Knowledge Sources** in the sidebar, then click **Add Source**. This opens a **3-step wizard**:

1. **Configure** — set the name, namespace, and provider type
2. **Review & Create** — confirm the settings and create the source (the canonical directory path is auto-generated)
3. **Upload Files** — for filesystem-backed sources, a file manager appears where you can upload files immediately

Every source needs:
- **Name** — a human-readable label (e.g., "Company Docs")
- **Namespace** — a short slug used for scoping searches and as the directory name (e.g., `docs`)
- **Provider Type** — how the source provides its data

Choose the provider type that matches your data:

### Filesystem

The universal filesystem-backed provider. Accepts any parsable file format: PDFs, text files, Markdown, Word documents, spreadsheets, JSON, YAML, XML, HTML, and CSV.

1. Select **Filesystem** as the provider type
2. The directory path is auto-generated: `storage/app/knowledge/filesystem/{namespace}/`
3. Optionally enter a custom path to override the canonical location
4. Complete the wizard — on step 3 you can upload files directly

Files can be uploaded through the UI or placed directly on the server filesystem. Both methods work together — the file manager shows files from both sources.

**Managing files:** On the source detail page, a full **File Manager** is available with:
- **Upload** — drag and drop files or use the upload button
- **Browse** — sortable file list with type-specific icons, file sizes, and pipeline status
- **Filter** — filter by indexed, pending, or error status
- **Delete** — remove files with confirmation

### Markdown Files

For `.md` and `.mdx` files — documentation, guides, knowledge bases, and **AI agent skills**. The Markdown provider chunks on heading boundaries, so each section becomes an independently searchable piece of context that agents can retrieve.

1. Select **Markdown Files** as the provider type
2. The canonical directory is `storage/app/knowledge/markdown/{namespace}/`
3. Upload markdown files via the file manager or place them directly on the server

### SQL Database

Connect to a database table and make its rows searchable.

1. Select **SQL Database** as the provider type
2. Choose a connection method:
   - **Named connection** — use a connection defined in `config/database.php` (e.g., `pgsql`)
   - **Custom credentials** — enter host, port, database name, username, and password directly (passwords are encrypted at rest)
3. Enter the **Table** name (e.g., `products`, `articles`)
4. Click **Create**

Each row in the table becomes a searchable document during sync.

### Web Crawler

Crawl a website and make its pages searchable.

1. Select **Web Crawler** as the provider type
2. Enter the **URLs** — one per line (e.g., `https://docs.example.com`)
3. Click **Create**

During sync, the system fetches each URL, converts the HTML to Markdown, and indexes the content. For recursive crawling (following links), configure the crawl settings in the source's detail page.

### YAML / JSON Files

Point to a directory containing YAML or JSON configuration files.

1. Select **YAML Files** or **JSON Files** as the provider type
2. The canonical directories are `storage/app/knowledge/yaml/{namespace}/` and `storage/app/knowledge/json/{namespace}/`
3. Upload files via the UI or place them directly on the server

> **Why separate types for YAML/JSON?** The YAML and JSON providers search **key-value pairs individually** — each entry in a YAML file or JSON object becomes a separate search hit. This gives you structured, field-level results that a generic Filesystem source can't provide. Use these types when your YAML/JSON contains structured records, configs, or data catalogs rather than prose documents.

---

## Syncing and Indexing

Processing is **automatic** — whenever you upload files, create a source, or update its configuration, the pipeline dispatches automatically:

```
Discover → Parse → Normalize → Chunk → Enrich → Index
```

- **Parse:** Apache Tika extracts text content (supports 35+ formats)
- **Normalize:** Whitespace and formatting are cleaned up
- **Chunk:** Documents are split using a content-type-aware strategy (heading-aware for Markdown, paragraph-aware for prose, sliding-window for structured data)
- **Enrich:** Metadata and embedding hashes are attached to each chunk
- **Index:** Chunks are stored in Typesense for fast semantic retrieval

> Processing happens in the background. Make sure your **queue worker is running** (`php artisan horizon`).

You can monitor progress on the source detail page — each document's status updates as it moves through the pipeline. 
If you ever need to manually trigger a re-sync, use the **Sync Now** button on the source detail page.

---

## Searching Your Knowledge

### Search Playground

The **Search Playground** lets you test queries and see exactly what the system retrieves:

1. Click **Playground** in the sidebar
2. Type a search query (e.g., "invoice policy" or "API documentation")
3. Optionally select a **namespace** to scope the search
4. Click **Search**

Results show:
- Matching document filenames
- Content snippets
- Relevance scores
- Which provider returned each result

### How Search Works

The Query Planner automatically:
1. Identifies which providers are relevant to your query
2. Executes searches in parallel across all matching sources
3. Merges results using Reciprocal Rank Fusion (RRF)
4. Returns a unified list ranked by relevance

Searches cover both document content and filenames.

---

## Managing Sources

From the **Knowledge Sources** list, you can:

- **Edit** — change name, namespace, provider type, or configuration
- **Delete** — remove the source and all its documents
- **Toggle active/inactive** — temporarily disable a source without deleting it
- **View details** — see document counts, provider status, and recent documents

On the source detail page, you can also edit the **provider configuration** — either through the structured form or as raw JSON.

---

## MCP: Connecting AI Agents

Agentoom Knowledge exposes a **Model Context Protocol (MCP)** endpoint that AI agents (like Claude Desktop, Cursor, or custom agents) can connect to.

### Creating an API Key

1. Navigate to **MCP → API Keys** in the sidebar
2. Click **Create API Key**
3. Give it a name (e.g., "Claude Desktop")
4. Select scopes: `mcp:use` for read-only access, `admin:*` for full access
5. Copy the generated key — it won't be shown again

### Connecting an MCP Client

Configure your MCP client to connect to:

```
https://your-domain.com/ai/mcp
```

Use the API key as a Bearer token in the `Authorization` header.

### Available MCP Tools

| Tool | Description |
|------|-------------|
| `search_knowledge` | Search across all knowledge sources |
| `list_sources` | List available knowledge sources with their namespaces |
| `get_source_schema` | Get the schema (fields, resources) for a specific namespace |

The MCP server returns structured JSON responses that AI agents can process directly.

---

## Federation

Agentoom Knowledge servers can be federated so one instance queries multiple servers transparently.

### Adding a Federated Server

1. Navigate to **Federation → Servers** in the sidebar
2. Click **Add Server**
3. Enter the remote server's URL and API token
4. Set a priority (lower numbers = higher priority)
5. Click **Save**

During searches, the Query Planner automatically includes federated servers alongside local providers. Results from all servers are merged with local results taking precedence.

---

## Users and Roles

Agentoom Knowledge has three roles:

| Role | Capabilities |
|------|-------------|
| **Admin** | Full access: manage sources, providers, users, API keys, settings |
| **Operator** | Manage sources, providers, and view documents — cannot manage users or settings |
| **Viewer** | Read-only access to dashboard, sources, and search playground |

Manage users from **Users** in the sidebar (admin only).

---

## Settings

The **Settings** page (sidebar → Settings) centralizes all system configuration across multiple tabs:

### Rate Limiting

Protect the MCP API endpoint from abuse:
- **Enable/disable** rate limiting with a toggle
- **Requests per minute:** set the maximum number of requests each API key can make per minute (default: 60)
- When a key exceeds the limit, subsequent requests receive `429 Too Many Requests` until the window resets

### Maintenance

Configure periodic maintenance tasks:
- **Federation sync interval:** how often remote server capabilities are refreshed (default: 15 minutes)
- **Log pruning:** automatically delete old application log files to conserve disk space (configurable retention)
- **Retrieval log pruning:** automatically delete old search history from the database (configurable retention)

### Notifications

Configure where and when operational alerts are sent:
- **Email notifications:** toggle on/off, enter one or more comma-separated email addresses
- **Webhook notifications:** toggle on/off, enter a Slack/Discord/Teams webhook URL — a preview of the JSON payload format is shown
- **Thresholds:** set the search latency alert threshold (ms), consecutive sync failure count before alerting, and the cooldown window between duplicate alerts
- **Alert types:** individually enable/disable alerts for search latency, sync failures, and federation failures

All notification settings take effect immediately — no restart required.

---

## Troubleshooting

### Health Check

Quickly verify all critical services are running:

```bash
curl http://localhost/health
```

Returns `{"status":"ok",...}` when database, Redis, Typesense, and storage are all healthy.

### Resetting the application

Need a clean slate? Navigate to **Settings → Danger Zone** and click **Reset Application**. This permanently deletes all knowledge sources, documents, indexed data, retrieval logs, and pipeline history. User accounts, API keys, and server configuration are preserved.

### Documents stuck in "discovered" status

The queue worker may not be processing jobs. Run:

```bash
php artisan horizon
```

Or process a specific source manually:

```bash
php artisan tinker --execute "
\$source = App\Knowledge\Models\KnowledgeSource::find(1);
app(App\DocumentPipeline\Services\PipelineOrchestrator::class)->run(\$source);
"
```

### Search returns no results

1. Make sure the **metadata registry** is built:
   ```bash
   php artisan knowledge:registry:refresh
   ```
2. Verify documents have been processed — check the source detail page for document statuses
3. Ensure the **Vector Store** configuration exists and Typesense is running
4. Try searching in the **Playground** without a namespace filter

### Apache Tika fails to parse a file

Tika must be running and accessible. Check:

```bash
curl http://localhost:9998/tika
```

If Tika is running but a specific file fails, check the document's error message on the source detail page.

### Files uploaded but not visible in search

Processing is automatic, but if documents appear stuck in "discovered" or "chunked" status, the queue worker may not be running:

```bash
php artisan horizon
```

### Queue jobs failing

Check the failed jobs:

```bash
php artisan queue:failed
```

Retry all failed jobs:

```bash
php artisan queue:retry all
```
