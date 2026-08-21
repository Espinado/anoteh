<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <x-pwa-head />

        <title>{{ isset($header) ? strip_tags($header) . ' · ' : '' }}{{ config('app.name', 'Anoteh') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full bg-slate-100 font-sans text-slate-800 antialiased" x-data="{ sidebarOpen: false, accountOpen: false }" @keydown.escape.window="sidebarOpen = false; accountOpen = false">
        <div class="min-h-full">
            <div x-cloak x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-slate-950/50 lg:hidden" @click="sidebarOpen = false"></div>
            <aside class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col bg-slate-950 text-white transition-transform duration-200 lg:translate-x-0" :class="{ 'translate-x-0': sidebarOpen }">
                <div class="flex h-16 items-center justify-between border-b border-white/10 px-5">
                    <a href="{{ route('vehicles.index') }}" wire:navigate class="flex items-center gap-3">
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-blue-600 text-lg font-bold">A</span>
                        <span><strong class="block text-lg">Anoteh</strong><small class="text-slate-400">{{ __('app.fleet_management') }}</small></span>
                    </a>
                    <button @click="sidebarOpen = false" class="grid min-h-11 min-w-11 place-items-center rounded-xl text-2xl text-slate-400 hover:bg-white/10 lg:hidden" aria-label="{{ __('app.close') }}">×</button>
                </div>
                <nav class="flex-1 space-y-1 overflow-y-auto p-3">
                    <a href="{{ route('vehicles.index') }}" wire:navigate @click="sidebarOpen = false" class="sidebar-link {{ request()->routeIs('vehicles.*') ? 'active' : '' }}">
                        <span class="w-6 text-center text-base text-slate-400">▣</span><span>{{ __('app.vehicles') }}</span>
                    </a>
                </nav>
                <div class="border-t border-white/10 p-4">
                    <p class="truncate text-sm font-semibold">{{ auth()->user()->name }}</p>
                    <p class="truncate text-xs text-slate-400">{{ auth()->user()->email }}</p>
                </div>
            </aside>

            <div class="lg:pl-72">
                <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
                    <div class="relative flex min-h-16 items-center gap-2 px-3 py-2 sm:gap-4 sm:px-6 lg:px-8">
                        <button @click="sidebarOpen = true" class="grid min-h-11 min-w-11 place-items-center rounded-xl text-xl text-slate-600 hover:bg-slate-100 lg:hidden" aria-label="{{ __('app.menu') }}">☰</button>
                        <div data-page-header class="min-w-0 flex-1 [&_h1]:truncate [&_h1]:text-xl sm:[&_h1]:text-2xl [&_p]:hidden sm:[&_p]:block">{{ $header ?? '' }}</div>
                        <div class="hidden items-center gap-1 sm:flex">
                            <a href="{{ route('locale.switch', app()->getLocale() === 'lv' ? 'ru' : 'lv') }}" class="rounded-lg px-2.5 py-2 text-xs font-bold uppercase text-slate-600 hover:bg-slate-100">{{ app()->getLocale() === 'lv' ? 'RU' : 'LV' }}</a>
                            <a href="{{ route('profile') }}" wire:navigate class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">{{ __('app.profile') }}</a>
                            <form method="POST" action="{{ route('logout') }}">@csrf<button class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">{{ __('app.logout') }}</button></form>
                        </div>
                        <button @click="accountOpen = !accountOpen" class="grid min-h-11 min-w-11 place-items-center rounded-xl text-xl font-bold text-slate-600 hover:bg-slate-100 sm:hidden" :aria-expanded="accountOpen" aria-label="{{ __('app.account_menu') }}">⋮</button>
                        <div x-cloak x-show="accountOpen" x-transition.origin.top.right @click.outside="accountOpen = false" class="absolute right-3 top-[3.75rem] z-40 w-52 overflow-hidden rounded-2xl border border-slate-200 bg-white p-2 shadow-xl sm:hidden">
                            <a href="{{ route('locale.switch', app()->getLocale() === 'lv' ? 'ru' : 'lv') }}" class="flex min-h-11 items-center rounded-xl px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">{{ __('app.language') }}: {{ app()->getLocale() === 'lv' ? 'RU' : 'LV' }}</a>
                            <a href="{{ route('profile') }}" wire:navigate @click="accountOpen = false" class="flex min-h-11 items-center rounded-xl px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">{{ __('app.profile') }}</a>
                            <form method="POST" action="{{ route('logout') }}">@csrf<button class="flex min-h-11 w-full items-center rounded-xl px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">{{ __('app.logout') }}</button></form>
                        </div>
                    </div>
                </header>
                <main class="safe-bottom p-3 pb-6 sm:p-6 lg:p-8">{{ $slot }}</main>
            </div>
        </div>
        <aside id="pwa-update-banner" class="update-banner hidden" role="alert" aria-live="assertive">
            <div class="grid grid-cols-[auto_1fr] items-center gap-3 sm:grid-cols-[auto_1fr_auto]">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-amber-400 text-xl" aria-hidden="true">↻</span>
                <div class="min-w-0 flex-1">
                    <h2 class="font-bold text-slate-950">{{ __('app.update_available') }}</h2>
                    <p class="mt-0.5 text-sm text-slate-700">{{ __('app.update_available_message') }}</p>
                </div>
                <button data-pwa-update class="btn-primary col-span-2 w-full sm:col-span-1 sm:w-auto">{{ __('app.update_now') }}</button>
            </div>
        </aside>
        <aside id="pwa-install-banner" class="install-banner hidden" role="dialog" aria-labelledby="pwa-install-title">
            <div class="flex items-start gap-3">
                <img src="/images/icons/icon-192.png" alt="" class="h-11 w-11 shrink-0 rounded-xl">
                <div class="min-w-0 flex-1">
                    <h2 id="pwa-install-title" class="font-bold text-slate-950">{{ __('app.install_app') }}</h2>
                    <p class="mt-1 text-sm leading-5 text-slate-600">{{ __('app.install_app_message') }}</p>
                    <p data-pwa-ios class="mt-2 hidden rounded-xl bg-blue-50 p-3 text-sm font-medium text-blue-900">{{ __('app.install_ios_instructions') }}</p>
                </div>
                <button data-pwa-dismiss class="grid min-h-11 min-w-11 place-items-center rounded-xl text-xl text-slate-500 hover:bg-slate-100" aria-label="{{ __('app.close') }}">×</button>
            </div>
            <div class="mt-3 flex justify-end">
                <button data-pwa-install class="btn-primary hidden w-full sm:w-auto">{{ __('app.install') }}</button>
            </div>
        </aside>
        <div id="global-loading" class="fixed inset-0 z-[100] hidden place-items-center bg-slate-950/30 backdrop-blur-[1px]">
            <div class="rounded-2xl bg-white px-6 py-5 shadow-2xl"><div class="mx-auto h-9 w-9 animate-spin rounded-full border-4 border-blue-100 border-t-blue-700"></div><p class="mt-3 text-sm font-semibold">{{ __('app.loading') }}</p></div>
        </div>
    </body>
</html>
