<?php

namespace App\Settings;

use App\Contracts\SettingsManager as SettingsManagerContract;
use App\Events\SettingsChanged;
use App\Events\SystemOnFallbackConfig;
use App\Models\Setting;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Auth;
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

    public function getMany(array $keys, array $defaults = []): array
    {
        if ($this->fallbackMode) {
            return $this->getManyFromConfig($keys, $defaults);
        }

        try {
            $values = [];
            $missingKeys = [];

            // Read from cache first
            foreach ($keys as $key) {
                $cached = $this->cache->get("setting:{$key}");

                if ($cached !== null) {
                    $values[$key] = $this->castValue($cached['value'], $cached['type']);
                } else {
                    $missingKeys[] = $key;
                }
            }

            // Batch-fetch missing keys from DB
            if ($missingKeys !== []) {
                $settings = Setting::whereIn('key', $missingKeys)->get();

                foreach ($settings as $setting) {
                    $this->cache->put("setting:{$setting->key}", [
                        'value' => $setting->value,
                        'type' => $setting->type,
                    ], now()->addMinutes(5));

                    $values[$setting->key] = $this->castValue($setting->value, $setting->type);
                }
            }

            // Fill in defaults for keys still missing
            foreach ($keys as $key) {
                if (! array_key_exists($key, $values)) {
                    $values[$key] = array_key_exists($key, $defaults)
                        ? $defaults[$key]
                        : $this->getFromConfig($key, $defaults[$key] ?? null);
                }
            }

            return $values;
        } catch (\Throwable $e) {
            Log::warning("SettingsManager: getMany falling back to config. Error: {$e->getMessage()}");

            if (! $this->fallbackMode) {
                $this->fallbackMode = true;
                event(new SystemOnFallbackConfig);
            }

            return $this->getManyFromConfig($keys, $defaults);
        }
    }

    public function set(string $key, mixed $value, string $type = 'string'): void
    {
        // Capture old value for audit logging
        $old = Setting::where('key', $key)->first();
        $oldValue = $old ? $this->castValue($old->value, $old->type) : null;

        Setting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $this->serializeValue($value, $type),
                'type' => $type,
            ]
        );

        $this->cache->forget("setting:{$key}");
        $this->fallbackMode = false;

        event(new SettingsChanged(
            key: $key,
            oldValue: $oldValue,
            newValue: $value,
            type: $type,
            userId: Auth::id(),
        ));
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

    /**
     * @param  array<int, string>  $keys
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function getManyFromConfig(array $keys, array $defaults = []): array
    {
        $values = [];

        foreach ($keys as $key) {
            $values[$key] = Config::get($key, $defaults[$key] ?? null);
        }

        return $values;
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
