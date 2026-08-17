<?php

namespace App\Mcp\Resources\Concerns;

use App\Knowledge\Models\KnowledgeSource;

/**
 * Resolves a knowledge source from a URI identifier.
 *
 * Numeric ids are tried first, then slug, then namespace — mirroring how
 * the source is addressed by the MCP browsing resources.
 */
trait ResolvesKnowledgeSources
{
    protected function resolveSource(string $identifier): ?KnowledgeSource
    {
        if ($identifier === '') {
            return null;
        }

        if (ctype_digit($identifier)) {
            $source = KnowledgeSource::find((int) $identifier);

            if ($source !== null) {
                return $source;
            }
        }

        return KnowledgeSource::where('slug', $identifier)
            ->orWhere('namespace', $identifier)
            ->first();
    }
}
