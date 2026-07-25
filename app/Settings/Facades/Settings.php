<?php

namespace App\Settings\Facades;

use App\Settings\SettingsManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed get(string $key, mixed $default = null)
 * @method static void set(string $key, mixed $value, string $type = 'string')
 * @method static void forget(string $key)
 * @method static array all(string $group)
 * @method static bool isUsingFallback()
 *
 * @see SettingsManager
 */
class Settings extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'settings';
    }
}
