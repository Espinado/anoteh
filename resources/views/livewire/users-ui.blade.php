<div>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-slate-500">{{ __('app.fleet_management') }}</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('app.users') }}</h1>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>
    @endif

    @if ($mode === 'index')
        <div class="mb-5 grid gap-3 lg:grid-cols-[minmax(0,1fr)_auto_auto] lg:items-end">
            <label class="field">
                <span class="sr-only">{{ __('app.search') }}</span>
                <input wire:model.live.debounce.300ms="search" type="search" class="w-full lg:max-w-md" placeholder="{{ __('app.search') }}">
            </label>
            <div class="grid grid-cols-2 gap-3 md:grid-cols-[minmax(0,16rem)_7rem]">
                <label class="field">
                    <span>{{ __('app.sort') }}</span>
                    <select wire:change="setSort($event.target.value)">
                        @foreach ([
                            'first_name:asc' => __('app.users_sort.name_asc'),
                            'first_name:desc' => __('app.users_sort.name_desc'),
                            'email:asc' => __('app.users_sort.email_asc'),
                            'role:asc' => __('app.users_sort.role_asc'),
                            'created_at:desc' => __('app.users_sort.newest'),
                        ] as $value => $label)
                            <option value="{{ $value }}" @selected($sort.':'.$direction === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field">
                    <span>{{ __('app.per_page') }}</span>
                    <select wire:model.live="perPage">
                        @foreach ([10, 15, 25] as $size)
                            <option value="{{ $size }}">{{ $size }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            @can('create', App\Models\User::class)
                <a href="{{ route('users.create') }}" wire:navigate class="btn-primary min-h-11 w-full justify-center lg:w-auto">{{ __('app.create') }}</a>
            @endcan
        </div>

        <section class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm md:block">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            @foreach ([
                                'first_name' => __('app.fields.full_name'),
                                'email' => __('app.fields.email'),
                                'role' => __('app.fields.role'),
                                'created_at' => __('app.users_sort.created'),
                            ] as $column => $label)
                                <th>
                                    <button type="button" wire:click="sortBy('{{ $column }}')" class="inline-flex min-h-11 items-center gap-1 text-left hover:text-blue-700">
                                        <span>{{ $label }}</span>
                                        <span aria-hidden="true">{{ $sort === $column ? ($direction === 'asc' ? '↑' : '↓') : '↕' }}</span>
                                    </button>
                                </th>
                            @endforeach
                            <th class="text-right">{{ __('app.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($records as $user)
                            <tr wire:key="user-row-{{ $user->id }}">
                                <td class="font-semibold">{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    <span class="status-badge">{{ __('app.statuses.'.$user->role->value) }}</span>
                                </td>
                                <td>{{ $user->created_at?->format('d.m.Y') ?? '—' }}</td>
                                <td>
                                    <div class="flex justify-end gap-1">
                                        @if ($user->is(auth()->user()))
                                            <a class="action-link" href="{{ route('profile') }}" wire:navigate>{{ __('app.open_profile') }}</a>
                                        @else
                                            @can('view', $user)<a class="action-link" href="{{ route('users.show', $user) }}" wire:navigate>{{ __('app.open') }}</a>@endcan
                                            @can('manage', $user)<a class="action-link" href="{{ route('users.edit', $user) }}" wire:navigate>{{ __('app.edit') }}</a>@endcan
                                        @endif
                                        @can('delete', $user)<button class="action-link text-red-600 hover:bg-red-50" wire:click="delete({{ $user->id }})" wire:confirm="{{ __('app.confirm_delete') }}">{{ __('app.delete') }}</button>@endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="empty-state">{{ __('app.no_records') }}</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="grid gap-3 md:hidden" aria-label="{{ __('app.users') }}">
            @forelse ($records as $user)
                <article wire:key="user-card-{{ $user->id }}" class="card !p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="truncate text-lg font-bold text-slate-950">{{ $user->name }}</h2>
                            <p class="mt-1 truncate text-sm text-slate-600">{{ $user->email }}</p>
                            @if ($user->phone)
                                <p class="mt-1 text-sm text-slate-500">{{ $user->phone }}</p>
                            @endif
                        </div>
                        <span class="status-badge shrink-0">{{ __('app.statuses.'.$user->role->value) }}</span>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-2">
                        @if ($user->is(auth()->user()))
                            <a class="btn-primary col-span-2 min-h-11 justify-center" href="{{ route('profile') }}" wire:navigate>{{ __('app.open_profile') }}</a>
                        @else
                            @can('view', $user)<a class="btn-primary min-h-11 justify-center" href="{{ route('users.show', $user) }}" wire:navigate>{{ __('app.open') }}</a>@endcan
                            @can('manage', $user)<a class="btn-secondary min-h-11 justify-center" href="{{ route('users.edit', $user) }}" wire:navigate>{{ __('app.edit') }}</a>@endcan
                        @endif
                        @can('delete', $user)<button class="btn-secondary col-span-2 min-h-11 justify-center !border-red-200 !text-red-700" wire:click="delete({{ $user->id }})" wire:confirm="{{ __('app.confirm_delete') }}">{{ __('app.delete') }}</button>@endcan
                    </div>
                </article>
            @empty
                <div class="empty-state bg-white">{{ __('app.no_records') }}</div>
            @endforelse
        </section>

        @if ($records->hasPages())
            <div class="mt-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="mb-3 text-center text-xs font-medium text-slate-500 sm:text-left">
                    {{ __('app.pagination_summary', ['from' => $records->firstItem(), 'to' => $records->lastItem(), 'total' => $records->total()]) }}
                </p>
                {{ $records->links() }}
            </div>
        @endif
    @elseif (in_array($mode, ['create', 'edit'], true))
        <form wire:submit="save" class="card w-full max-w-4xl">
            @if ($mode === 'create')
                <p class="mb-4 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">{{ __('app.invitation.create_hint') }}</p>
            @endif
            <div class="grid gap-4 sm:gap-5 md:grid-cols-2">
                <label class="field">
                    <span>{{ __('app.fields.first_name') }}</span>
                    <input wire:model="form.first_name" type="text" autocomplete="given-name">
                    @error('form.first_name')<small class="text-red-600">{{ $message }}</small>@enderror
                </label>
                <label class="field">
                    <span>{{ __('app.fields.last_name') }}</span>
                    <input wire:model="form.last_name" type="text" autocomplete="family-name">
                    @error('form.last_name')<small class="text-red-600">{{ $message }}</small>@enderror
                </label>
                <label class="field">
                    <span>{{ __('app.fields.email') }}</span>
                    <input wire:model="form.email" type="email" autocomplete="email">
                    @error('form.email')<small class="text-red-600">{{ $message }}</small>@enderror
                </label>
                <label class="field">
                    <span>{{ __('app.fields.phone') }}</span>
                    <input wire:model="form.phone" type="tel" autocomplete="tel">
                    @error('form.phone')<small class="text-red-600">{{ $message }}</small>@enderror
                </label>
                <label class="field md:col-span-2">
                    <span>{{ __('app.fields.role') }}</span>
                    <select wire:model="form.role">
                        @foreach ([App\Enums\UserRole::Admin, App\Enums\UserRole::Manager] as $role)
                            <option value="{{ $role->value }}">{{ __('app.statuses.'.$role->value) }}</option>
                        @endforeach
                    </select>
                    @error('form.role')<small class="text-red-600">{{ $message }}</small>@enderror
                </label>
            </div>
            <div class="vehicle-form-actions">
                <a href="{{ route('users.index') }}" wire:navigate class="btn-secondary min-h-11 justify-center">{{ __('app.cancel') }}</a>
                <button class="btn-primary min-h-11 justify-center">{{ $mode === 'create' ? __('app.create_and_invite') : __('app.save') }}</button>
            </div>
        </form>
    @else
        <section class="card w-full max-w-4xl">
            <div class="vehicle-show-head">
                <div class="min-w-0">
                    <h2 class="truncate text-xl font-bold sm:text-2xl">{{ $record->name }}</h2>
                    <p class="truncate text-sm text-slate-500 sm:text-base">{{ $record->email }}</p>
                </div>
                @can('manage', $record)
                    <a href="{{ route('users.edit', $record) }}" wire:navigate class="btn-secondary min-h-11">{{ __('app.edit') }}</a>
                @else
                    @if ($record->is(auth()->user()))
                        <a href="{{ route('profile') }}" wire:navigate class="btn-secondary min-h-11">{{ __('app.open_profile') }}</a>
                    @endif
                @endcan
            </div>
            @if ($record->is(auth()->user()))
                <p class="mt-4 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">{{ __('app.users_self_hint') }}</p>
            @endif
            <dl class="mt-4 grid gap-3 sm:mt-6 sm:grid-cols-2 sm:gap-4">
                <div class="rounded-xl bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('app.fields.first_name') }}</dt>
                    <dd class="mt-1 font-medium">{{ $record->first_name }}</dd>
                </div>
                <div class="rounded-xl bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('app.fields.last_name') }}</dt>
                    <dd class="mt-1 font-medium">{{ $record->last_name }}</dd>
                </div>
                <div class="rounded-xl bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('app.fields.email') }}</dt>
                    <dd class="mt-1 font-medium">{{ $record->email }}</dd>
                </div>
                <div class="rounded-xl bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('app.fields.phone') }}</dt>
                    <dd class="mt-1 font-medium">{{ $record->phone ?: '—' }}</dd>
                </div>
                <div class="rounded-xl bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('app.fields.role') }}</dt>
                    <dd class="mt-1 font-medium">{{ __('app.statuses.'.$record->role->value) }}</dd>
                </div>
                <div class="rounded-xl bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('app.users_sort.created') }}</dt>
                    <dd class="mt-1 font-medium">{{ $record->created_at?->format('d.m.Y H:i') ?? '—' }}</dd>
                </div>
            </dl>
            @can('invite', $record)
                <div class="mt-6 border-t border-slate-200 pt-4">
                    <p class="mb-3 text-sm text-slate-600">{{ __('app.invitation.resend_hint') }}</p>
                    <button type="button" wire:click="resendInvitation({{ $record->id }})" class="btn-secondary min-h-11 w-full justify-center sm:w-auto">{{ __('app.resend_invitation') }}</button>
                </div>
            @endcan
            @can('delete', $record)
                <div class="mt-4 border-t border-slate-200 pt-4">
                    <button type="button" wire:click="delete({{ $record->id }})" wire:confirm="{{ __('app.confirm_delete') }}" class="btn-secondary min-h-11 w-full justify-center !border-red-200 !text-red-700 sm:w-auto">{{ __('app.delete') }}</button>
                </div>
            @endcan
        </section>
    @endif
</div>
