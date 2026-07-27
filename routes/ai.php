<?php

use App\Mcp\Servers\KnowledgeServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', KnowledgeServer::class)->middleware(['auth:mcp_api', 'throttle:mcp-api']);
Mcp::local('knowledge', KnowledgeServer::class);
