<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\KnowledgeSearchGuide;
use App\Mcp\Tools\GetSourceSchema;
use App\Mcp\Tools\ListSources;
use App\Mcp\Tools\SearchKnowledge;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Agentoom Knowledge')]
#[Version('1.0.0')]
#[Instructions('Agentoom Knowledge provides unified search across all configured knowledge sources. Use `search_knowledge` for all search operations — the server determines the best retrieval strategy internally. Use `list_sources` to discover available sources. Use `get_source_schema` to examine a source\'s structure.')]
class KnowledgeServer extends Server
{
    protected array $tools = [
        SearchKnowledge::class,
        ListSources::class,
        GetSourceSchema::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        KnowledgeSearchGuide::class,
    ];
}
