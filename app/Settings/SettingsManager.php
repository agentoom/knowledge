<?php

namespace App\Settings;

use App\Contracts\SettingsManager as SettingsManagerContract;
use App\Events\SystemOnFallbackConfig;
use App\Models\Setting;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class SettingsManager implements SettingsManagerContract
{
    private bool $fallbackMode = false;

    public function __construct(private readonly Cache $cache) {}

    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->fallbackMode) {
            return $this->getFromConfig($key, $default);
        }

        try {
            $cached = $this->cache->get("setting:{$key}");

            if ($cached !== null) {
                return $this->castValue($cached['value'], $cached['type']);
            }

            $setting = Setting::where('key', $key)->first();

            if ($setting) {
                $this->cache->put("setting:{$key}", [
                    'value' => $setting->value,
                    'type' => $setting->type,
                ], now()->addMinutes(5));

                return $this->castValue($setting->value, $setting->type);
            }

            return $this->getFromConfig($key, $default);
        } catch (\Throwable $e) {
            Log::warning("SettingsManager: falling back to config for key '{$key}'. Error: {$e->getMessage()}");

            if (! $this->fallbackMode) {
                $this->fallbackMode = true;
                event(new SystemOnFallbackConfig);
            }

            return $this->getFromConfig($key, $default);
        }
    }

    public function set(string $key, mixed $value, string $type = 'string'): void
    {
        Setting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $this->serializeValue($value, $type),
                'type' => $type,
            ]
        );

        $this->cache->forget("setting:{$key}");
        $this->fallbackMode = false;
    }

    public function forget(string $key): void
    {
        Setting::where('key', $key)->delete();
        $this->cache->forget("setting:{$key}");
    }

    public function all(string $group): array
    {
        try {
            return Setting::where('group', $group)
                ->get()
                ->mapWithKeys(fn (Setting $setting) => [
                    $setting->key => $this->castValue($setting->value, $setting->type),
                ])
                ->all();
        } catch (\Throwable) {
            return Config::get($group, []);
        }
    }

    public function isUsingFallback(): bool
    {
        return $this->fallbackMode;
    }

    private function getFromConfig(string $key, mixed $default): mixed
    {
        return Config::get($key, $default);
    }

    private function castValue(?string $value, string $type): mixed
    {
        return match ($type) {
            'integer' => (int) $value,
            'boolean' => (bool) $value,
            'json' => json_decode($value ?? '{}', true),
            'encrypted' => $value ? decrypt($value) : null,
            default => $value,
        };
    }

    private function serializeValue(mixed $value, string $type): string
    {
        return match ($type) {
            'json' => json_encode($value),
            'encrypted' => encrypt($value),
            default => (string) $value,
        };
    }
}
