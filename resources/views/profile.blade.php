<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-slate-500">{{ __('app.fleet_management') }}</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('app.profile') }}</h1>
        </div>
    </x-slot>

    <div class="grid max-w-5xl gap-5">
        <section class="card">
            <div class="max-w-2xl">
                <livewire:profile.update-profile-information-form />
            </div>
        </section>

        <section class="card">
            <div class="max-w-2xl">
                <livewire:profile.update-password-form />
            </div>
        </section>

        <section class="card">
            <div class="max-w-2xl">
                <livewire:profile.delete-user-form />
            </div>
        </section>
    </div>
</x-app-layout>
