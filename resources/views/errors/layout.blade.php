{{-- Shared layout for all error pages: same look as the app, and a way back. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="flex min-h-screen items-center justify-center bg-white p-6 dark:bg-zinc-800">
        <div class="flex max-w-md flex-col items-center gap-4 text-center">
            <x-app-logo-icon class="size-10 text-zinc-800 dark:text-white" />
            <flux:heading size="xl" level="1">@yield('code') · @yield('title')</flux:heading>
            <flux:text>@yield('message')</flux:text>
            <flux:button variant="primary" :href="route('notes.index')">{{ __('Back to my notes') }}</flux:button>
        </div>
    </body>
</html>
