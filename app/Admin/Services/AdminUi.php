<?php

namespace App\Admin\Services;

use Illuminate\Support\Collection;

class AdminUi
{
    /** @var Collection<int, array{route: string, label: string, icon: string, group: string, permission: string|null}> */
    private Collection $pages;

    /** @var Collection<int, array{key: string, type: string, group: string, label: string}> */
    private Collection $registeredSettings;

    public function __construct()
    {
        $this->pages = collect();
        $this->registeredSettings = collect();
    }

    public function registerPage(
        string $route,
        string $label,
        string $icon,
        string $group = 'Plugins',
        ?string $permission = null,
    ): void {
        $this->pages->push([
            'route' => $route,
            'label' => $label,
            'icon' => $icon,
            'group' => $group,
            'permission' => $permission,
        ]);
    }

    /**
     * @param  array<string, array{type: string, group: string, label: string}>  $settings
     */
    public function registerSettings(array $settings): void
    {
        foreach ($settings as $key => $config) {
            $this->registeredSettings->push([
                'key' => $key,
                'type' => $config['type'],
                'group' => $config['group'],
                'label' => $config['label'],
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    public function getSidebarGroups(): array
    {
        return $this->pages
            ->pluck('group')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array{route: string, label: string, icon: string, group: string, permission: string|null}>
     */
    public function getPages(): Collection
    {
        return $this->pages;
    }

    /**
     * @return Collection<int, array{route: string, label: string, icon: string, group: string, permission: string|null}>
     */
    public function getPagesByGroup(string $group): Collection
    {
        return $this->pages->where('group', $group);
    }

    /**
     * @return Collection<int, array{key: string, type: string, group: string, label: string}>
     */
    public function getRegisteredSettings(): Collection
    {
        return $this->registeredSettings;
    }
}
