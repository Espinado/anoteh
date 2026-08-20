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
        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <input wire:model.live.debounce.300ms="search" type="search" class="max-w-md" placeholder="{{ __('app.search') }}">
            @can('create', App\Models\Vehicle::class)
                <a href="{{ route('vehicles.create') }}" wire:navigate class="btn-primary">{{ __('app.create') }}</a>
            @endcan
        </div>
        <section class="card !p-0 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ __('app.fields.registration_number') }}</th>
                            <th>{{ __('app.fields.make') }} / {{ __('app.fields.model') }}</th>
                            <th>{{ __('app.fields.year') }}</th>
                            <th>{{ __('app.fields.inspection_until') }}</th>
                            <th>{{ __('app.fields.octa_until') }}</th>
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
                                    <div class="flex justify-end gap-3">
                                        @can('view', $vehicle)<a class="link" href="{{ route('vehicles.show', $vehicle) }}" wire:navigate>{{ __('app.open') }}</a>@endcan
                                        @can('update', $vehicle)<a class="link" href="{{ route('vehicles.edit', $vehicle) }}" wire:navigate>{{ __('app.edit') }}</a>@endcan
                                        @can('delete', $vehicle)<button class="text-sm font-semibold text-red-600" wire:click="delete({{ $vehicle->id }})" wire:confirm="{{ __('app.confirm_delete') }}">{{ __('app.delete') }}</button>@endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><div class="empty-state">{{ __('app.no_records') }}</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t p-4">{{ $records->links() }}</div>
        </section>
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
            <div class="mt-8 flex justify-end gap-3">
                <a href="{{ route('vehicles.index') }}" wire:navigate class="btn-secondary">{{ __('app.cancel') }}</a>
                <button class="btn-primary">{{ __('app.save') }}</button>
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
