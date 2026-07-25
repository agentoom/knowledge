<?php

namespace App\Knowledge\Models;

use Database\Factories\KnowledgeSourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<string, mixed>|null $provider_config
 *
 * @use HasFactory<KnowledgeSourceFactory>
 */
class KnowledgeSource extends Model
{
    /** @use HasFactory<KnowledgeSourceFactory> */
    use HasFactory;

    protected static function newFactory(): KnowledgeSourceFactory
    {
        return KnowledgeSourceFactory::new();
    }

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'provider_type',
        'provider_config',
        'namespace',
        'is_active',
        'priority',
        'config_version',
    ];

    protected function casts(): array
    {
        return [
            'provider_config' => 'array',
            'is_active' => 'boolean',
            'config_version' => 'integer',
        ];
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * @return HasMany<Provider, $this>
     */
    public function providers(): HasMany
    {
        return $this->hasMany(Provider::class);
    }
}
