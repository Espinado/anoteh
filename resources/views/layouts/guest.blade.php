<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <x-pwa-head />

        <title>{{ config('app.name', 'Anoteh') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full bg-slate-100 font-sans text-slate-900 antialiased">
        <main class="safe-bottom flex min-h-full items-center justify-center p-3 sm:p-6">
            <div class="w-full max-w-md">
                <a href="/" wire:navigate class="mx-auto mb-5 flex w-fit items-center gap-3 rounded-2xl px-3 py-2">
                    <span class="grid h-12 w-12 place-items-center rounded-2xl bg-blue-700 text-2xl font-bold text-white shadow-lg shadow-blue-200">A</span>
                    <span>
                        <strong class="block text-xl">Anoteh</strong>
                        <small class="text-slate-500">{{ __('app.fleet_management') }}</small>
                    </span>
                </a>
                <section class="card !p-5 sm:!p-7">
                    {{ $slot }}
                </section>
            </div>
        </main>
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
                    <h2 id="pwa-install-title" class="font-bold">{{ __('app.install_app') }}</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ __('app.install_app_message') }}</p>
                    <p data-pwa-ios class="mt-2 hidden rounded-xl bg-blue-50 p-3 text-sm font-medium text-blue-900">{{ __('app.install_ios_instructions') }}</p>
                </div>
                <button data-pwa-dismiss class="grid min-h-11 min-w-11 place-items-center rounded-xl text-xl text-slate-500" aria-label="{{ __('app.close') }}">×</button>
            </div>
            <button data-pwa-install class="btn-primary mt-3 hidden w-full">{{ __('app.install') }}</button>
        </aside>
    </body>
</html>
