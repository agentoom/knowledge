<?php

namespace App\Mcp\Resources;

use App\Knowledge\Enums\ProviderStatus;
use App\Knowledge\Models\Provider;
use App\Knowledge\Services\MetadataRegistryService;
use App\Knowledge\Services\ProviderManager;
use App\Mcp\Services\ResourceAuthorizationService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

#[Name('Knowledge Sources')]
#[Title('Knowledge Sources')]
#[Description('Lists all active knowledge sources with their provider metadata and namespaces.')]
#[Uri('knowledge://sources')]
class ListSourcesResource extends Resource
{
    public function handle(Request $request): Response
    {
        $authorization = app(ResourceAuthorizationService::class);

        if (! $authorization->authorize()) {
            return Response::error('Not authorized to list knowledge sources.');
        }

        $registry = app(MetadataRegistryService::class)->get();

        if (empty($registry)) {
            return Response::text((string) json_encode([
                'sources' => [],
                'namespaces' => [],
                'total_sources' => 0,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        // Resolve the active provider classes once — ProviderManager runs a
        // full providers-table query, so it must not be resolved per provider.
        $activeClasses = $this->activeProviderClasses();

        $providers = array_values(array_filter(
            $registry['providers'] ?? [],
            function (array $provider) use ($authorization, $activeClasses): bool {
                if (! in_array($provider['class'] ?? '', $activeClasses, true)) {
                    return false;
                }

                return $authorization->authorize($provider['namespace'] ?? null);
            }
        ));

        return Response::text((string) json_encode([
            'sources' => $providers,
            'namespaces' => array_values(array_unique(array_filter(array_column($providers, 'namespace')))),
            'total_sources' => count($providers),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Classes of providers that are active AND belong to an active source.
     *
     * @return array<int, string>
     */
    private function activeProviderClasses(): array
    {
        return app(ProviderManager::class)->all()
            ->filter(fn (Provider $provider): bool => $provider->status === ProviderStatus::Active->value
                && $provider->knowledgeSource?->is_active === true)
            ->pluck('class')
            ->all();
    }
}
