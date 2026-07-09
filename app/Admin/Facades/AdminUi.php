<?php

namespace App\Admin\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void registerPage(string $route, string $label, string $icon, string $group = 'Plugins', ?string $permission = null)
 * @method static void registerSettings(array $settings)
 * @method static array getSidebarGroups()
 * @method static \Illuminate\Support\Collection getPages()
 * @method static \Illuminate\Support\Collection getPagesByGroup(string $group)
 * @method static \Illuminate\Support\Collection getRegisteredSettings()
 *
 * @see \App\Admin\Services\AdminUi
 */
class AdminUi extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'admin-ui';
    }
}
