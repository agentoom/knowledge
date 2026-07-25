<?php

namespace App\VectorStore\Services;

use App\Contracts\VectorStore as VectorStoreContract;
use App\VectorStore\Drivers\TypesenseVectorStore;
use App\VectorStore\Models\VectorStore;
use InvalidArgumentException;

class VectorStoreManager
{
    private ?VectorStoreContract $driver = null;

    private ?string $defaultDriverName = null;

    public function driver(?string $name = null): VectorStoreContract
    {
        if ($name === null) {
            $name = $this->getDefaultDriver();
        }

        if ($this->driver && $name === $this->defaultDriverName) {
            return $this->driver;
        }

        return $this->resolve($name);
    }

    public function getDefaultDriver(): string
    {
        return $this->defaultDriverName ??= VectorStore::where('is_active', true)
            ->value('driver') ?? 'typesense';
    }

    public function capabilities(?string $driverName = null): array
    {
        return $this->driver($driverName)->capabilities();
    }

    private function resolve(string $name): VectorStoreContract
    {
        $config = VectorStore::where('driver', $name)->first()?->config ?? [];

        return match ($name) {
            'typesense' => new TypesenseVectorStore($config),
            default => throw new InvalidArgumentException("Vector store driver [{$name}] is not supported."),
        };
    }
}
