<x-layouts::auth :title="__('Log in')">
    <x-auth-branding />

    <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-white/10 dark:bg-white/10 space-y-6">
        <flux:heading>{{ __('Log in') }}</flux:heading>

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-passkey-verify />

        <form method="POST" action="{{ route('login.store') }}" class="space-y-6">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="current-password"
                :placeholder="__('Password')"
                viewable
            />

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

            <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                {{ __('Log in') }}
            </flux:button>
        </form>

        @if (Route::has('password.request'))
            <p class="text-center text-sm text-zinc-500 dark:text-white/70">
                <flux:link :href="route('password.request')" wire:navigate>{{ __('Forgot your password?') }}</flux:link>
            </p>
        @endif
    </div>
</x-layouts::auth>
