<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800 flex items-center justify-center">
        <div class="w-full max-w-md mx-auto px-4">
            <div class="text-center mb-8">
                <x-app-logo class="mx-auto h-10 w-auto" />
            </div>

            <flux:card class="text-center">
                <div class="mb-6">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30 mb-4">
                        <flux:icon.shield-exclamation class="h-8 w-8 text-red-600 dark:text-red-400" />
                    </div>

                    <flux:heading size="xl" class="mb-2">Access Denied</flux:heading>

                    <flux:text class="text-zinc-500 dark:text-zinc-400">
                        You do not have the required permissions to access this area.
                    </flux:text>
                </div>

                @auth
                    <flux:text class="text-sm text-zinc-400 dark:text-zinc-500 mb-6">
                        You are signed in as <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ auth()->user()->name }}</span>
                        ({{ auth()->user()->email }}).
                    </flux:text>

                    <div class="flex flex-col gap-3">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <flux:button type="submit" variant="primary" icon="arrow-right-start-on-rectangle" class="w-full">
                                {{ __('Log out') }}
                            </flux:button>
                        </form>

                        <flux:button :href="route('home')" variant="ghost" wire:navigate>
                            Go to home page
                        </flux:button>
                    </div>
                @else
                    <flux:button :href="route('login')" variant="primary" class="w-full">
                        {{ __('Log in') }}
                    </flux:button>
                @endauth
            </flux:card>
        </div>

        @fluxScripts
    </body>
</html>
