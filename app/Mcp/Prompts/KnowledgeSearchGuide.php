<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Description('Guidance on how to effectively use the Agentoom Knowledge server for retrieving enterprise knowledge.')]
class KnowledgeSearchGuide extends Prompt
{
    /**
     * Handle the prompt request.
     */
    public function handle(Request $request): Response
    {
        $guide = <<<'GUIDE'
You are connected to Agentoom Knowledge — a self-hosted enterprise knowledge server.

### How to Search Effectively

1. **Start with `list_sources`** to discover what knowledge is available. Optionally filter by namespace (e.g., `docs`, `erp`, `hr`).

2. **Use `get_source_schema`** to understand the structure of a specific source before querying it — this reveals available fields, filters, and capabilities.

3. **Search with `search_knowledge`** — this is your primary retrieval tool. The server internally determines whether to use semantic search (vector), structured query (SQL), or both (hybrid). You do NOT need to choose the retrieval method.

   - **`query`** (required): Your search query — be specific and precise.
   - **`namespace`** (optional): Scope to a specific namespace when you know the domain (e.g., `"hr"` for HR documents, `"erp"` for business data).
   - **`filters`** (optional): For structured sources, pass field filters like `{"status": "active", "category": "support"}`.
   - **`search_type`** (optional): Use `"semantic"` for conceptual/natural language queries, `"structured"` for exact field matching, or `"hybrid"` (default) to let the server decide.
   - **`max_results`** (optional): Limit results (default: 10).

### Key Principles

- **You never need to know where data comes from.** The Query Planner routes to the right provider automatically.
- **Be precise in your queries.** Specific terms yield better results than vague questions.
- **Use namespaces to scope searches.** If you know the user is asking about HR policies, scope to the `hr` namespace.
- **Use filters for structured data.** When querying business entities (orders, customers, tickets), provide relevant filters to narrow results.

### Example Workflow

1. User asks: "Find our vacation policy and tell me how many days I get."
2. Call `list_sources` with `namespace: "hr"` to confirm HR sources exist.
3. Call `search_knowledge` with `query: "vacation policy days", namespace: "hr"`.
4. Review results and answer the user.
GUIDE;

        return Response::text($guide);
    }

    /**
     * Get the prompt's arguments.
     *
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [];
    }
}
