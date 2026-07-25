<?php

namespace App\Admin\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void registerPage(string $route, string $label, string $icon, string $group = 'Plugins', ?string $permission = null)
 * @method static void registerSettings(array<string, array{type: string, group: string, label: string}> $settings)
 * @method static array<int, string> getSidebarGroups()
 * @method static \Illuminate\Support\Collection<int, array{route: string, label: string, icon: string, group: string, permission: string|null}> getPages()
 * @method static \Illuminate\Support\Collection<int, array{route: string, label: string, icon: string, group: string, permission: string|null}> getPagesByGroup(string $group)
 * @method static \Illuminate\Support\Collection<int, array{key: string, type: string, group: string, label: string}> getRegisteredSettings()
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
