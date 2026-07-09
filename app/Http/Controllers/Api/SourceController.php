<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Knowledge\Services\MetadataRegistryService;
use Illuminate\Http\JsonResponse;

class SourceController extends Controller
{
    public function index(MetadataRegistryService $registry): JsonResponse
    {
        $data = $registry->get();

        return response()->json([
            'sources' => $data['providers'] ?? [],
            'namespaces' => $data['namespaces'] ?? [],
        ]);
    }

    public function schema(string $id, MetadataRegistryService $registry): JsonResponse
    {
        $data = $registry->get();
        $schemas = $data['schemas'] ?? [];

        if (! isset($schemas[$id])) {
            return response()->json(['error' => 'Source not found.'], 404);
        }

        return response()->json([
            'source_id' => $id,
            'schema' => $schemas[$id],
        ]);
    }
}
