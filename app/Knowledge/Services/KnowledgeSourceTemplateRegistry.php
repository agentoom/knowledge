<?php

namespace App\Knowledge\Services;

use InvalidArgumentException;

/**
 * Reads the versioned source templates from config/knowledge.php.
 *
 * Templates are presets, not a database-managed CRUD system. Only the label,
 * default name, namespace, description, provider type, and provider config
 * are exposed; secrets never ship inside a template.
 */
class KnowledgeSourceTemplateRegistry
{
    /**
     * Provider types the create wizard and observer support.
     *
     * @var array<int, string>
     */
    private const SUPPORTED_PROVIDERS = ['filesystem', 'sql', 'yaml', 'json', 'markdown', 'web'];

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $templates = [];

        foreach (config('knowledge.source_templates', []) as $key => $template) {
            if ($this->isSupported($template)) {
                $templates[$key] = $this->expose($key, $template);
            }
        }

        return $templates;
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $key): array
    {
        $templates = config('knowledge.source_templates', []);

        if (! isset($templates[$key])) {
            throw new InvalidArgumentException("Knowledge source template [{$key}] does not exist.");
        }

        if (! $this->isSupported($templates[$key])) {
            throw new InvalidArgumentException("Knowledge source template [{$key}] uses an unsupported provider type.");
        }

        return $this->expose($key, $templates[$key]);
    }

    /**
     * @param  array<string, mixed>  $template
     */
    private function isSupported(array $template): bool
    {
        return in_array($template['provider_type'] ?? '', self::SUPPORTED_PROVIDERS, true);
    }

    /**
     * @param  array<string, mixed>  $template
     * @return array<string, mixed>
     */
    private function expose(string $key, array $template): array
    {
        return [
            'key' => $key,
            'label' => $template['label'] ?? $key,
            'default_name' => $template['default_name'] ?? '',
            'namespace' => $template['namespace'] ?? '',
            'description' => $template['description'] ?? '',
            'provider_type' => $template['provider_type'] ?? '',
            'provider_config' => $template['provider_config'] ?? [],
        ];
    }
}
