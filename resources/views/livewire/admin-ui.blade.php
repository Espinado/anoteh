<div>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-slate-500">{{ __('app.fleet_management') }}</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('app.vehicles') }}</h1>
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
                            'registration_number:asc' => __('app.sort_options.registration_asc'),
                            'registration_number:desc' => __('app.sort_options.registration_desc'),
                            'make:asc' => __('app.sort_options.make_asc'),
                            'make:desc' => __('app.sort_options.make_desc'),
                            'year:desc' => __('app.sort_options.year_desc'),
                            'year:asc' => __('app.sort_options.year_asc'),
                            'inspection_until:asc' => __('app.sort_options.inspection_asc'),
                            'octa_until:asc' => __('app.sort_options.octa_asc'),
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
            @can('create', App\Models\Vehicle::class)
                <a href="{{ route('vehicles.create') }}" wire:navigate class="btn-primary min-h-11 w-full justify-center lg:w-auto">{{ __('app.create') }}</a>
            @endcan
        </div>

        <section class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm md:block">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            @foreach ([
                                'registration_number' => __('app.fields.registration_number'),
                                'make' => __('app.fields.make').' / '.__('app.fields.model'),
                                'year' => __('app.fields.year'),
                                'inspection_until' => __('app.fields.inspection_until'),
                                'octa_until' => __('app.fields.octa_until'),
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
                        @forelse ($records as $vehicle)
                            <tr>
                                <td class="font-semibold">{{ $vehicle->registration_number }}</td>
                                <td>{{ $vehicle->make }} {{ $vehicle->model }}</td>
                                <td>{{ $vehicle->year ?: '—' }}</td>
                                <td>{{ $vehicle->inspection_until?->format('d.m.Y') ?? '—' }}</td>
                                <td>{{ $vehicle->octa_until?->format('d.m.Y') ?? '—' }}</td>
                                <td>
                                    <div class="flex justify-end gap-1">
                                        @can('view', $vehicle)<a class="action-link" href="{{ route('vehicles.show', $vehicle) }}" wire:navigate>{{ __('app.open') }}</a>@endcan
                                        @can('update', $vehicle)<a class="action-link" href="{{ route('vehicles.edit', $vehicle) }}" wire:navigate>{{ __('app.edit') }}</a>@endcan
                                        @can('delete', $vehicle)<button class="action-link text-red-600 hover:bg-red-50" wire:click="delete({{ $vehicle->id }})" wire:confirm="{{ __('app.confirm_delete') }}">{{ __('app.delete') }}</button>@endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><div class="empty-state">{{ __('app.no_records') }}</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="grid gap-3 md:hidden" aria-label="{{ __('app.vehicles') }}">
            @forelse ($records as $vehicle)
                <article wire:key="vehicle-card-{{ $vehicle->id }}" class="card !p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('app.fields.registration_number') }}</p>
                            <h2 class="mt-0.5 truncate text-xl font-bold text-slate-950">{{ $vehicle->registration_number }}</h2>
                            <p class="mt-1 text-sm font-medium text-slate-600">{{ $vehicle->make }} {{ $vehicle->model }}{{ $vehicle->year ? ' · '.$vehicle->year : '' }}</p>
                        </div>
                        <span class="status-badge">{{ $vehicle->fuel_type ? __('app.statuses.'.$vehicle->fuel_type->value) : '—' }}</span>
                    </div>
                    <dl class="mt-4 grid grid-cols-2 gap-3">
                        <div class="rounded-xl bg-slate-50 p-3">
                            <dt class="text-xs font-medium text-slate-500">{{ __('app.fields.inspection_until') }}</dt>
                            <dd class="mt-1 font-semibold text-slate-900">{{ $vehicle->inspection_until?->format('d.m.Y') ?? '—' }}</dd>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3">
                            <dt class="text-xs font-medium text-slate-500">{{ __('app.fields.octa_until') }}</dt>
                            <dd class="mt-1 font-semibold text-slate-900">{{ $vehicle->octa_until?->format('d.m.Y') ?? '—' }}</dd>
                        </div>
                    </dl>
                    <div class="mt-4 grid grid-cols-2 gap-2">
                        @can('view', $vehicle)<a class="btn-primary min-h-11 justify-center" href="{{ route('vehicles.show', $vehicle) }}" wire:navigate>{{ __('app.open') }}</a>@endcan
                        @can('update', $vehicle)<a class="btn-secondary min-h-11 justify-center" href="{{ route('vehicles.edit', $vehicle) }}" wire:navigate>{{ __('app.edit') }}</a>@endcan
                        @can('delete', $vehicle)<button class="btn-secondary col-span-2 min-h-11 justify-center !border-red-200 !text-red-700" wire:click="delete({{ $vehicle->id }})" wire:confirm="{{ __('app.confirm_delete') }}">{{ __('app.delete') }}</button>@endcan
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
        <form wire:submit="save" class="card max-w-4xl">
            <div class="grid gap-5 md:grid-cols-2">
                @foreach (['registration_number', 'make', 'model', 'year', 'vin', 'fuel_type', 'inspection_until', 'octa_until'] as $field)
                    <label class="field">
                        <span>{{ __('app.fields.'.$field) }}</span>
                        @if ($field === 'fuel_type')
                            <select wire:model="form.fuel_type">
                                <option value="petrol">{{ __('app.statuses.petrol') }}</option>
                                <option value="diesel">{{ __('app.statuses.diesel') }}</option>
                            </select>
                        @elseif (in_array($field, ['inspection_until', 'octa_until'], true))
                            <input wire:model="form.{{ $field }}" type="date">
                        @elseif ($field === 'year')
                            <input wire:model="form.year" type="number" min="1900" max="{{ now()->year + 1 }}">
                        @else
                            <input wire:model="form.{{ $field }}" type="text">
                        @endif
                        @error('form.'.$field)<small class="text-red-600">{{ $message }}</small>@enderror
                    </label>
                @endforeach
            </div>
            <div class="mt-8 grid gap-3 sm:flex sm:justify-end">
                <a href="{{ route('vehicles.index') }}" wire:navigate class="btn-secondary min-h-11 justify-center">{{ __('app.cancel') }}</a>
                <button class="btn-primary min-h-11 justify-center">{{ __('app.save') }}</button>
            </div>
        </form>
    @else
        <section class="card max-w-4xl">
            <div class="flex items-center justify-between border-b pb-5">
                <div>
                    <h2 class="text-2xl font-bold">{{ $record->registration_number }}</h2>
                    <p class="text-slate-500">{{ $record->make }} {{ $record->model }}</p>
                </div>
                @can('update', $record)<a href="{{ route('vehicles.edit', $record) }}" wire:navigate class="btn-secondary">{{ __('app.edit') }}</a>@endcan
            </div>
            <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                @foreach (['registration_number', 'make', 'model', 'year', 'vin', 'fuel_type', 'inspection_until', 'octa_until'] as $field)
                    @php($value = $record->{$field})
                    <div class="rounded-xl bg-slate-50 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('app.fields.'.$field) }}</dt>
                        <dd class="mt-1 font-medium">{{ $value instanceof DateTimeInterface ? $value->format('d.m.Y') : ($value instanceof BackedEnum ? __('app.statuses.'.$value->value) : ($value ?: '—')) }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    @endif
</div>
