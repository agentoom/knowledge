<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('admin.dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Administration')" class="grid">
                    <flux:sidebar.item icon="chart-bar" :href="route('admin.dashboard')" :current="request()->routeIs('admin.dashboard')" wire:navigate>
                        Admin Dashboard
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="rocket-launch" :href="route('admin.playground')" :current="request()->routeIs('admin.playground')" wire:navigate>
                        Playground
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="document-text" :href="route('admin.knowledge-sources.index')" :current="request()->routeIs('admin.knowledge-sources.*')" wire:navigate>
                        Knowledge Sources
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="squares-plus" :href="route('admin.providers.index')" :current="request()->routeIs('admin.providers.*')" wire:navigate>
                        Providers
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="folder" :href="route('admin.documents.index')" :current="request()->routeIs('admin.documents.*')" wire:navigate>
                        Documents
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="list-bullet" :href="route('admin.retrieval-logs.index')" :current="request()->routeIs('admin.retrieval-logs.*')" wire:navigate>
                        Search Logs
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="server" :href="route('admin.vector-store.settings')" :current="request()->routeIs('admin.vector-store.*')" wire:navigate>
                        Vector Store
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="magnifying-glass" :href="route('admin.query-planner.settings')" :current="request()->routeIs('admin.query-planner.*')" wire:navigate>
                        Query Planner
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="key" :href="route('admin.mcp.api-keys')" :current="request()->routeIs('admin.mcp.*')" wire:navigate>
                        MCP
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="users" :href="route('admin.users.index')" :current="request()->routeIs('admin.users.*')" wire:navigate>
                        Users
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="heart" :href="route('admin.health')" :current="request()->routeIs('admin.health')" wire:navigate>
                        Health
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Settings')" class="grid">
                    <flux:sidebar.item icon="cog" :href="route('admin.settings.general')" :current="request()->routeIs('admin.settings.*')" wire:navigate>
                        Settings
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="queue-list" :href="route('admin.jobs.index')" :current="request()->routeIs('admin.jobs.*')" wire:navigate>
                        Jobs
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
