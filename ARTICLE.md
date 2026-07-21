# Agentoom Knowledge: A Self-Hosted MCP Knowledge Server for the Enterprise

**Knowledge is heterogeneous. Retrieval should be too.**

Enterprise knowledge doesn't live in one place. Documentation sits in Markdown files. Customer records live in PostgreSQL. Configuration is scattered across YAML files and JSON blobs. System status comes from live APIs. And increasingly, AI agents need to reason across all of it — not just one silo at a time.

Most retrieval systems try to force this diversity into a single strategy. Vectorize everything and hope semantic search figures it out. Or model everything as a graph and drown in complexity. Each approach is excellent within its domain, but no single strategy is optimal for every type of knowledge.

**Agentoom Knowledge** is built on a different premise: the retrieval strategy should match the nature of the knowledge. It is a self-hosted Knowledge Server that exposes a unified, trusted context window to AI agents through the Model Context Protocol (MCP). The AI never needs to know whether a piece of information came from a vector database, a legacy SQL table, or a cloud API — it simply receives the context it needs to perform its task.

---

## How It Works

At the heart of the system is a **Deterministic Query Planner** — inspired by database query optimizers — that decomposes every context request into a set of executable tasks. Critically, it does not rely on an LLM for planning. This choice delivers predictability, explainability, lower latency, and zero token costs during the retrieval phase.

The planner identifies which knowledge providers are relevant to a query, executes them in parallel using Laravel's concurrency layer, and merges results through **Reciprocal Rank Fusion (RRF)**. The result is a single, ranked context window drawn from every relevant source — filesystems, SQL databases, YAML configs, web content, vector stores, and even other federated Knowledge Server instances.

Knowledge flows into the system through an automated **Document Pipeline**: Discover → Parse → Chunk → Enrich → Index. Four content-aware chunking strategies are auto-selected based on MIME type — Markdown-aware splitting for documentation, semantic boundaries for prose, sliding windows for code, and fixed-size for fallback. Web crawling respects robots.txt, strips navigation and boilerplate, and converts HTML to clean Markdown via league/html-to-markdown.

Security is a first-class concern. All MCP access is guarded by scoped API keys with hashed storage and prefix-optimized lookups. Role-based access control (Admin, Operator, Viewer) governs the administration UI. Fortify-powered authentication supports passkeys and TOTP two-factor auth. Multi-tenancy is baked into the schema with `tenant_id` columns and foreign key constraints across all core models.

---

## Architecture

The codebase follows a clean, contract-driven architecture built on **Laravel 13** with **PHP 8.5**, **Livewire 4 + Flux UI**, **PostgreSQL**, **Redis**, and **Typesense**. Every major subsystem is behind an interface — `KnowledgeProvider`, `VectorStore`, `EmbeddingProvider`, `DocumentParser`, `ChunkingStrategy`, `PlannerStrategy`, `ResultFusionStrategy`, `SettingsManager` — making the entire retrieval stack swappable at the container level.

The domain is partitioned into clear bounded contexts:

- **Knowledge** — provider registry, source/document/chunk models, metadata cache
- **Retrieval** — query planner, parallel execution engine, RRF fusion
- **Planning** — deterministic, federation-aware planning strategies
- **Document Pipeline** — multi-stage orchestration with batched queue jobs
- **Vector Store** — Typesense driver with managed embeddings
- **Federation** — cross-server query federation via MCP
- **MCP** — three tools (`search_knowledge`, `list_sources`, `get_source_schema`) and one prompt, served over the Model Context Protocol
- **Admin** — 20+ Livewire components for full management of sources, providers, federation servers, users, and settings

The bundled providers cover the most common enterprise knowledge surfaces: Filesystem, SQL, YAML, JSON, Web (with recursive crawling), Vector (Typesense with managed embeddings), and Federation. Custom providers are registered through `config/knowledge.php` and auto-discovered — no plugin system to learn, just a contract to implement.

---

## Key Strengths

**Deterministic, not magical.** The query planner is rule-based and auditable. The same request always produces the same retrieval plan. Every execution is logged with full query text, step-by-step plans, fused results, and millisecond-precision latency. An interactive Search Playground lets administrators visualize the planner's reasoning alongside the evidence it retrieved.

**Strategy matches the data.** A SQL query for structured business data. Semantic search for documentation. Filesystem traversal for Markdown. Real-time HTTP fetch for web content. The system doesn't force a square peg into a round hole — it picks the right tool for each source.

**Self-hosted and private.** Everything runs on your infrastructure. No data leaves your network. Typesense handles embedding generation internally, avoiding the latency, cost, and privacy concerns of calling external LLM APIs during indexing.

**Extensible by design.** Eight contracts define the extension points. The `make:knowledge-provider` Artisan command scaffolds new providers. Custom vector stores, embedding models, chunking strategies, and planner algorithms are all swappable through Laravel's service container.

**Observable and debuggable.** Every retrieval is logged. The dashboard tracks real-time Horizon queue health and Typesense metrics. The Search Playground shows exactly which providers were queried, what each returned, and how results were fused — no black boxes.

**Federation-ready.** Multiple Agentoom Knowledge instances can be connected together. A single query fans out to all federated peers, results are fused with local results via RRF, and the AI receives one unified context window — regardless of how many servers contributed.

---

## Technical Notes

The server exposes three MCP tools over a single endpoint (`/mcp`):

- `search_knowledge` — unified search across all configured sources with automatic strategy selection
- `list_sources` — discover available knowledge sources and their namespaces
- `get_source_schema` — examine a source's structure, fields, and capabilities

All tools are protected by scoped API key authentication (`mcp:use`, `admin:*`) via a custom `mcp_api` guard. Keys are hashed with bcrypt and stored with an 8-character prefix for efficient database lookups.

The document pipeline runs on Laravel Horizon with configurable Redis-backed queues. Each stage (Discover → Parse → Chunk → Enrich → Index) is a discrete job class, allowing horizontal scaling of workers per stage based on workload.

Chunking strategies are content-type-aware and auto-selected by MIME type through a `ChunkingStrategyRegistry`:

| Strategy | Best For | Method |
|---|---|---|
| Markdown | Documentation | Heading-aware, falls back to paragraphs |
| Semantic | Prose, articles | Paragraph and sentence boundaries |
| Sliding Window | Code, structured data | Overlapping windows, no mid-thought breaks |
| Fixed Size | Unknown formats | Character-based with word-boundary respect |

The web provider supports recursive crawling with configurable depth, page limits, domain allowlisting, politeness delays, and full robots.txt compliance. Crawled content is stripped of navigation, headers, footers, scripts, and styles before conversion to clean Markdown.

---

**Agentoom Knowledge is MIT-licensed and available on GitHub. It is extracted from the architecture behind Agentoom and released as a standalone open-source project so the community can benefit from a high-quality, self-hosted knowledge infrastructure.**
