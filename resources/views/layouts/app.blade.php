<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($header) ? strip_tags($header) . ' · ' : '' }}{{ config('app.name', 'Anoteh') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full bg-slate-100 font-sans text-slate-800 antialiased" x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false">
        @php
            $menu = [
                ['dashboard', 'dashboard', '⌂'], ['vehicles.index', 'vehicles', '▣'],
                ['plans.index', 'maintenance_plans', '◷'], ['service-records.index', 'service_records', '✓'],
                ['defects.index', 'defects', '!'], ['expenses.index', 'expenses', '€'],
                ['reports.index', 'reports', '▥'], ['documents.index', 'documents', '▤'],
                ['notifications.index', 'notifications', '●'], ['profile', 'profile', '○'],
            ];
        @endphp
        <div class="min-h-full">
            <div x-cloak x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-slate-950/50 lg:hidden" @click="sidebarOpen = false"></div>
            <aside class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col bg-slate-950 text-white transition-transform duration-200 lg:translate-x-0" :class="{ 'translate-x-0': sidebarOpen }">
                <div class="flex h-18 items-center justify-between border-b border-white/10 px-5 py-4">
                    <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-3">
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-blue-600 text-lg font-bold">A</span>
                        <span><strong class="block text-lg">Anoteh</strong><small class="text-slate-400">{{ __('app.fleet_management') }}</small></span>
                    </a>
                    <button @click="sidebarOpen = false" class="p-2 text-slate-400 lg:hidden" aria-label="{{ __('app.close') }}">×</button>
                </div>
                <nav class="flex-1 space-y-1 overflow-y-auto p-3">
                    @foreach($menu as [$routeName, $label, $icon])
                        <a href="{{ route($routeName) }}" wire:navigate @click="sidebarOpen = false" class="sidebar-link {{ request()->routeIs(str_replace('.index', '.*', $routeName)) || request()->routeIs($routeName) ? 'active' : '' }}">
                            <span class="w-6 text-center text-base text-slate-400">{{ $icon }}</span><span>{{ __('app.'.$label) }}</span>
                            @if($routeName === 'notifications.index' && auth()->user()->unreadNotifications()->count())<span class="ml-auto rounded-full bg-red-500 px-2 py-0.5 text-xs">{{ auth()->user()->unreadNotifications()->count() }}</span>@endif
                        </a>
                    @endforeach
                    @if((data_get(auth()->user(), 'role.value') ?? auth()->user()->role ?? null) === 'admin')
                        <a href="{{ route('users.index') }}" wire:navigate class="sidebar-link {{ request()->routeIs('users.*') ? 'active' : '' }}"><span class="w-6 text-center text-slate-400">♙</span>{{ __('app.users') }}</a>
                        <a href="{{ route('audit.index') }}" wire:navigate class="sidebar-link {{ request()->routeIs('audit.*') ? 'active' : '' }}"><span class="w-6 text-center text-slate-400">⌕</span>{{ __('app.audit') }}</a>
                    @endif
                </nav>
                <div class="border-t border-white/10 p-4">
                    <p class="truncate text-sm font-semibold">{{ auth()->user()->name }}</p>
                    <p class="truncate text-xs text-slate-400">{{ auth()->user()->email }}</p>
                </div>
            </aside>

            <div class="lg:pl-72">
                <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
                    <div class="flex min-h-18 items-center gap-4 px-4 py-3 sm:px-6 lg:px-8">
                        <button @click="sidebarOpen = true" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden" aria-label="{{ __('app.menu') }}">☰</button>
                        <div class="min-w-0 flex-1">{{ $header ?? '' }}</div>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('locale.switch', app()->getLocale() === 'lv' ? 'ru' : 'lv') }}" class="rounded-lg px-2.5 py-2 text-xs font-bold uppercase text-slate-600 hover:bg-slate-100">{{ app()->getLocale() === 'lv' ? 'RU' : 'LV' }}</a>
                            <form method="POST" action="{{ route('logout') }}">@csrf<button class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">{{ __('app.logout') }}</button></form>
                        </div>
                    </div>
                </header>
                <main class="p-4 sm:p-6 lg:p-8">{{ $slot }}</main>
            </div>
        </div>
        <div id="global-loading" class="fixed inset-0 z-[100] hidden place-items-center bg-slate-950/30 backdrop-blur-[1px]">
            <div class="rounded-2xl bg-white px-6 py-5 shadow-2xl"><div class="mx-auto h-9 w-9 animate-spin rounded-full border-4 border-blue-100 border-t-blue-700"></div><p class="mt-3 text-sm font-semibold">{{ __('app.loading') }}</p></div>
        </div>
    </body>
</html>
