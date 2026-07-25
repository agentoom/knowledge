<?php

namespace App\Knowledge\Services;

use App\Contracts\KnowledgeProvider;
use App\Knowledge\Models\Provider;
use Illuminate\Support\Collection;

class ProviderManager
{
    /** @var Collection<int, Provider> */
    private Collection $providers;

    public function __construct()
    {
        $this->providers = collect();
        $this->discoverProviders();
    }

    /**
     * @return Collection<int, Provider>
     */
    public function all(): Collection
    {
        return $this->providers;
    }

    /**
     * @return Collection<int, Provider>
     */
    public function getByType(string $type): Collection
    {
        return $this->providers->filter(
            fn (Provider $provider) => $provider->type === $type
        );
    }

    public function getByClass(string $class): ?Provider
    {
        return $this->providers->first(
            fn (Provider $provider) => $provider->class === $class
        );
    }

    /**
     * Get all instantiated KnowledgeProvider instances for consumers that need them.
     *
     * @return Collection<int, KnowledgeProvider>
     */
    public function getKnowledgeProviders(): Collection
    {
        return $this->providers
            ->map(fn (Provider $provider) => $provider->toKnowledgeProvider())
            ->filter()
            ->values();
    }

    public function getCount(): int
    {
        return $this->providers->count();
    }

    public function refresh(): void
    {
        $this->providers = collect();
        $this->discoverProviders();
    }

    private function discoverProviders(): void
    {
        $this->providers = Provider::query()
            ->with('knowledgeSource')
            ->get();
    }
}
