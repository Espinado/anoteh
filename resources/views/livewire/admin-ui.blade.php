<div>
    @php
        $titles = [
            'dashboard' => __('app.dashboard'), 'vehicles' => __('app.vehicles'),
            'templates' => __('app.maintenance_templates'), 'plans' => __('app.maintenance_plans'),
            'service-records' => __('app.service_records'), 'defects' => __('app.defects'),
            'expenses' => __('app.expenses'), 'reports' => __('app.reports'),
            'documents' => __('app.documents'), 'notifications' => __('app.notifications'),
            'users' => __('app.users'), 'audit' => __('app.audit'),
        ];
        $fieldOptions = [
            'status' => match($section) {
                'vehicles' => $this->optionValues('vehicle_status'),
                'plans' => $this->optionValues('plan_status'),
                'defects' => $this->defectStatusOptions(),
                'service-records' => $this->serviceStatusOptions(),
                'documents' => $this->optionValues('document_status'),
                default => [],
            },
            'severity' => $this->optionValues('severity'),
            'category' => match($section) {
                'vehicles' => $this->optionValues('vehicle_category'),
                'templates' => $this->optionValues('category_template'),
                default => $this->optionValues('expense_category'),
            },
            'service_type' => $this->optionValues('service_type'),
            'fuel_type' => $this->optionValues('fuel_type'),
            'type' => $this->optionValues('document_type'),
            'role' => $this->optionValues('role'),
        ];
        if (!in_array($section, ['vehicles', 'templates', 'expenses'])) {
            unset($fieldOptions['category']);
        }
        if ($section !== 'documents') {
            unset($fieldOptions['type']);
        }
        if ($section !== 'service-records') {
            unset($fieldOptions['service_type']);
        }
        if ($section !== 'vehicles') {
            unset($fieldOptions['fuel_type']);
        }
    @endphp

    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-slate-500">{{ __('app.fleet_management') }}</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $titles[$section] ?? ucfirst($section) }}</h1>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if ($section === 'dashboard')
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['active_vehicles', 'active_vehicles', 'bg-blue-50 text-blue-700'],
                ['in_repair', 'in_repair', 'bg-cyan-50 text-cyan-700'],
                ['overdue', 'overdue', 'bg-red-50 text-red-700'],
                ['soon', 'soon', 'bg-amber-50 text-amber-700'],
                ['critical_defects', 'critical_defects', 'bg-orange-50 text-orange-700'],
                ['expiring_documents', 'expiring_documents', 'bg-violet-50 text-violet-700'],
                ['costs_month', 'costs_month', 'bg-emerald-50 text-emerald-700'],
                ['costs_year', 'costs_year', 'bg-teal-50 text-teal-700'],
            ] as [$key, $label, $color])
                <button type="button" wire:click="setDashboardFilter('{{ $key }}')" class="card text-left transition hover:-translate-y-0.5 hover:shadow-md">
                    <span class="inline-flex rounded-lg px-2.5 py-1 text-xs font-semibold {{ $color }}">{{ __('app.'.$label) }}</span>
                    <strong class="mt-5 block text-3xl font-bold text-slate-900">{{ str_starts_with($key, 'costs_') ? number_format((float) ($dashboard[$key] ?? 0), 2).' €' : ($dashboard[$key] ?? 0) }}</strong>
                    <span class="mt-1 block text-xs text-slate-500">{{ __('app.click_to_filter') }}</span>
                </button>
            @endforeach
        </div>
        <section class="card mt-6">
            <h2 class="section-title">{{ __('app.recent_service_events') }}</h2>
            <div class="mt-4 divide-y">
                @forelse($recentServiceEvents as $event)
                    <a href="{{ route('service-records.show', $event) }}" wire:navigate class="flex items-center justify-between gap-4 py-3">
                        <span><strong>{{ $event->vehicle->registration_number ?? '#'.$event->vehicle_id }}</strong><small class="ml-2 text-slate-500">{{ $event->provider_name }}</small></span>
                        <span class="text-sm text-slate-500">{{ $this->formatDate($event->completed_at ?? $event->updated_at) }}</span>
                    </a>
                @empty <div class="empty-state">{{ __('app.no_records') }}</div> @endforelse
            </div>
        </section>
        <div class="mt-6 grid gap-6 xl:grid-cols-3">
            <section class="card xl:col-span-2">
                <div class="flex items-center justify-between">
                    <h2 class="section-title">{{ __('app.upcoming_maintenance') }}</h2>
                    <a href="{{ route('plans.index') }}" wire:navigate class="text-sm font-semibold text-blue-700">{{ __('app.view_all') }}</a>
                </div>
                <div class="mt-4 divide-y rounded-xl border">
                    @forelse($upcomingMaintenance as $plan)
                        <a href="{{ route('plans.show', $plan) }}" wire:navigate class="flex items-center justify-between gap-4 p-4 transition hover:bg-slate-50">
                            <span><strong class="block">{{ $plan->vehicle->registration_number ?? ('#'.$plan->vehicle_id) }}</strong><small class="text-slate-500">{{ $plan->template->name ?? __('app.maintenance') }}</small></span>
                            <span class="text-right"><span class="status-badge">{{ __('app.statuses.'.$this->value($plan->status)) }}</span><small class="mt-1 block text-slate-500">{{ $plan->next_due_date?->format('d.m.Y') ?? $plan->next_due_odometer }}</small></span>
                        </a>
                    @empty <div class="empty-state m-4">{{ __('app.no_records') }}</div> @endforelse
                </div>
            </section>
            <section class="card">
                <h2 class="section-title">{{ __('app.quick_actions') }}</h2>
                <div class="mt-4 grid gap-2">
                    @can('create', App\Models\Vehicle::class)<a href="{{ route('vehicles.create') }}" wire:navigate class="btn-primary justify-center">{{ __('app.add_vehicle') }}</a>@endcan
                    @can('create', App\Models\ServiceRecord::class)<a href="{{ route('service-records.create') }}" wire:navigate class="btn-secondary justify-center">{{ __('app.add_service_record') }}</a>@endcan
                    @can('create', App\Models\Defect::class)<a href="{{ route('defects.create') }}" wire:navigate class="btn-secondary justify-center">{{ __('app.report_defect') }}</a>@endcan
                </div>
            </section>
        </div>
    @elseif ($section === 'reports')
        <section class="card">
            <form method="GET" action="{{ route('reports.download') }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <label class="field"><span>{{ __('app.report_type') }}</span>
                    <select name="type">@foreach(['expenses','expenses_by_category','cost_per_km','service_history','defects','maintenance_period','downtime','expiring_documents'] as $type)<option value="{{ $type }}" @selected(request('type', 'expenses') === $type)>{{ __('app.report_types.'.$type) }}</option>@endforeach</select>
                </label>
                <label class="field"><span>{{ __('app.vehicle') }}</span>
                    <select name="vehicle_id"><option value="">{{ __('app.all') }}</option>@foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}" @selected((string) request('vehicle_id') === (string) $vehicle->id)>{{ $vehicle->registration_number }}</option>@endforeach</select>
                </label>
                <label class="field"><span>{{ __('app.from') }}</span><input type="date" name="from" value="{{ request('from') }}"></label>
                <label class="field"><span>{{ __('app.to') }}</span><input type="date" name="to" value="{{ request('to') }}"></label>
                <label class="field"><span>{{ __('app.status') }}</span><input name="status" value="{{ request('status') }}"></label>
                <label class="field"><span>{{ __('app.fields.category') }}</span><select name="category"><option value="">{{ __('app.all') }}</option>@foreach($this->optionValues('expense_category') as $value)<option value="{{ $value }}" @selected(request('category') === $value)>{{ __('app.statuses.'.$value) }}</option>@endforeach</select></label>
                <div class="flex items-end gap-2 md:col-span-2">
                    <button name="format" value="csv" class="btn-secondary flex-1">CSV</button>
                    <button name="format" value="xlsx" class="btn-primary flex-1">XLSX</button>
                    <button type="submit" formaction="{{ route('reports.print') }}" formtarget="_blank" class="btn-secondary flex-1">{{ __('app.print') }}</button>
                </div>
            </form>
            <div class="empty-state mt-8">{{ __('app.select_report_filters') }}</div>
        </section>
    @elseif ($section === 'notifications')
        <div class="mb-4 flex justify-end"><button wire:click="markAllNotificationsRead" class="btn-secondary">{{ __('app.mark_all_read') }}</button></div>
        <div class="space-y-3">
            @forelse ($notifications as $notification)
                <button wire:click="markNotificationRead('{{ $notification->id }}')" class="card w-full text-left {{ $notification->read_at ? 'opacity-70' : 'border-l-4 border-l-blue-600' }}">
                    <div class="flex justify-between gap-4"><strong>{{ data_get($notification->data, 'title', __('app.notification')) }}</strong><time class="text-xs text-slate-500">{{ $this->formatDate($notification->created_at) }}</time></div>
                    <p class="mt-1 text-sm text-slate-600">{{ data_get($notification->data, 'message') }}</p>
                </button>
            @empty <div class="empty-state">{{ __('app.no_notifications') }}</div> @endforelse
        </div>
    @elseif ($mode === 'index')
        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-1 flex-col gap-3 sm:flex-row">
                <input wire:model.live.debounce.300ms="search" type="search" class="max-w-md" placeholder="{{ __('app.search') }}">
                @if (in_array($section, ['vehicles', 'plans', 'defects', 'service-records']))
                    <select wire:model.live="status" class="sm:max-w-48"><option value="">{{ __('app.all_statuses') }}</option>@foreach($fieldOptions['status'] as $value)<option value="{{ $value }}">{{ __('app.statuses.'.$value) }}</option>@endforeach</select>
                @endif
                @if(in_array($section, ['vehicles', 'templates', 'defects', 'expenses']))
                    <input wire:model.live.debounce.300ms="category" class="sm:max-w-48" placeholder="{{ __('app.fields.category') }}">
                @endif
                @if(auth()->user()->isAdmin() && $section !== 'audit')
                    <label class="inline-flex items-center gap-2 text-sm font-semibold"><input wire:model.live="trash" type="checkbox" class="rounded border-slate-300"> {{ __('app.trash') }}</label>
                @endif
            </div>
            <div class="flex gap-2">
                @if($section === 'plans')<a href="{{ route('templates.index') }}" wire:navigate class="btn-secondary">{{ __('app.maintenance_templates') }}</a>@endif
                @if($canCreate)<a href="{{ route($section.'.create') }}" wire:navigate class="btn-primary">{{ __('app.create') }}</a>@endif
            </div>
        </div>
        <section class="card !p-0 overflow-hidden">
            <div class="hidden overflow-x-auto md:block">
                <table class="data-table">
                    <thead><tr><th><button wire:click="sortBy('{{ $section === 'vehicles' ? 'registration_number' : ($section === 'audit' ? 'event' : ($section === 'users' ? 'name' : 'created_at')) }}')">{{ __('app.name') }} ↕</button></th><th>{{ __('app.details') }}</th><th><button wire:click="sortBy('{{ in_array($section, ['audit','expenses','templates']) ? 'created_at' : 'status' }}')">{{ __('app.status') }} ↕</button></th><th class="text-right">{{ __('app.actions') }}</th></tr></thead>
                    <tbody>
                    @forelse($records as $item)
                        <tr>
                            <td class="font-semibold text-slate-900">{{ $this->displayName($item) }}</td>
                            <td>{{ $this->displayDetails($item) }}</td>
                            <td><span class="status-badge">{{ $section === 'audit' ? $this->formatDate($item->created_at) : __('app.statuses.'.$this->value($item->status ?? $item->role ?? ($section === 'expenses' ? $item->category : (data_get($item, 'active', true) ? 'active' : 'inactive')))) }}</span></td>
                            <td><div class="flex justify-end gap-2">
                                @if($section !== 'audit' && !$item->trashed()) @can('view', $item)<a href="{{ route($section.'.show', $item) }}" wire:navigate class="link">{{ __('app.open') }}</a>@endcan @endif
                                @if($section !== 'audit' && !$item->trashed()) @can('update', $item)<a href="{{ route($section.'.edit', $item) }}" wire:navigate class="link">{{ __('app.edit') }}</a>@endcan @endif
                                @if($section !== 'audit' && !$item->trashed()) @can('delete', $item)<button wire:click="delete({{ $item->id }})" wire:confirm="{{ __('app.confirm_delete') }}" class="text-sm font-semibold text-red-600">{{ __('app.delete') }}</button>@endcan @endif
                                @if($section !== 'audit' && $item->trashed() && auth()->user()->isAdmin())<button wire:click="restore({{ $item->id }})" class="link">{{ __('app.restore') }}</button>@endif
                            </div></td>
                        </tr>
                    @empty <tr><td colspan="4"><div class="empty-state">{{ __('app.no_records') }}</div></td></tr> @endforelse
                    </tbody>
                </table>
            </div>
            <div class="divide-y md:hidden">
                @forelse($records as $item)
                    <article class="p-4">
                        <div class="flex items-start justify-between gap-3"><strong>{{ $this->displayName($item) }}</strong><span class="status-badge">{{ $section === 'audit' ? $this->formatDate($item->created_at) : __('app.statuses.'.$this->value($item->status ?? $item->role ?? ($section === 'expenses' ? $item->category : (data_get($item, 'active', true) ? 'active' : 'inactive')))) }}</span></div>
                        <p class="mt-2 text-sm text-slate-600">{{ $this->displayDetails($item) }}</p>
                        @if($section !== 'audit')<div class="mt-4 flex gap-4">@can('view', $item)<a href="{{ route($section.'.show', $item) }}" wire:navigate class="link">{{ __('app.open') }}</a>@endcan @can('update', $item)<a href="{{ route($section.'.edit', $item) }}" wire:navigate class="link">{{ __('app.edit') }}</a>@endcan @if($item->trashed() && auth()->user()->isAdmin())<button wire:click="restore({{ $item->id }})" class="link">{{ __('app.restore') }}</button>@endif</div>@endif
                    </article>
                @empty <div class="empty-state">{{ __('app.no_records') }}</div> @endforelse
            </div>
            @if(method_exists($records, 'links'))<div class="border-t p-4">{{ $records->links() }}</div>@endif
        </section>
    @elseif (in_array($mode, ['create', 'edit']))
        <form wire:submit="save" class="card max-w-4xl">
            <div class="grid gap-5 md:grid-cols-2">
                @foreach ($form as $field => $value)
                    @continue(in_array($field, ['odometer', 'odometer_notes', 'odometer_override', 'odometer_override_reason']) && $section === 'vehicles')
                    @continue($section === 'plans' && (($mode === 'create' && in_array($field, ['last_service_odometer', 'last_service_date', 'next_due_odometer', 'next_due_date', 'status', 'active'])) || ($mode === 'edit' && $field === 'starts_on')))
                    <label class="field {{ in_array($field, ['description', 'notes', 'internal_notes', 'recommended_operations']) ? 'md:col-span-2' : '' }}">
                        <span>{{ __('app.fields.'.$field) }}</span>
                        @if (isset($fieldOptions[$field]))
                            <select wire:model="form.{{ $field }}">@foreach($fieldOptions[$field] as $option)<option value="{{ $option }}">{{ __('app.statuses.'.$option) }}</option>@endforeach</select>
                        @elseif ($field === 'vehicle_id')
                            <select wire:model="form.vehicle_id"><option value="">{{ __('app.select') }}</option>@foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}">{{ $vehicle->registration_number }}</option>@endforeach</select>
                        @elseif ($field === 'maintenance_template_id')
                            <select wire:model="form.maintenance_template_id"><option value="">{{ __('app.select') }}</option>@foreach($templates as $template)<option value="{{ $template->id }}">{{ $template->name }}</option>@endforeach</select>
                        @elseif ($field === 'maintenance_plan_id')
                            <select wire:model="form.maintenance_plan_id"><option value="">{{ __('app.select') }}</option>@foreach($plans as $plan)<option value="{{ $plan->id }}">{{ $plan->vehicle->registration_number }} · {{ $plan->template->name }}</option>@endforeach</select>
                        @elseif ($field === 'defect_id')
                            <select wire:model="form.defect_id"><option value="">{{ __('app.select') }}</option>@foreach($defects as $defect)<option value="{{ $defect->id }}">{{ $defect->vehicle->registration_number }} · {{ $defect->title }}</option>@endforeach</select>
                        @elseif ($field === 'service_record_id')
                            <select wire:model="form.service_record_id"><option value="">{{ __('app.select') }}</option>@foreach($serviceRecords as $service)<option value="{{ $service->id }}">#{{ $service->id }} · {{ $service->provider_name }}</option>@endforeach</select>
                        @elseif ($field === 'assigned_to')
                            <select wire:model="form.assigned_to"><option value="">{{ __('app.select') }}</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select>
                        @elseif ($field === 'responsible_user_id')
                            <select wire:model="form.responsible_user_id"><option value="">{{ __('app.select') }}</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select>
                        @elseif ($field === 'primary_attachment_id')
                            <select wire:model="form.primary_attachment_id"><option value="">{{ __('app.select') }}</option>@foreach($attachments as $attachment)<option value="{{ $attachment->id }}">{{ $attachment->original_name }}</option>@endforeach</select>
                        @elseif ($field === 'active')
                            <select wire:model="form.active"><option value="1">{{ __('app.statuses.active') }}</option><option value="0">{{ __('app.statuses.inactive') }}</option></select>
                        @elseif (in_array($field, ['description', 'notes', 'internal_notes', 'recommended_operations']))
                            <textarea wire:model="form.{{ $field }}" rows="4"></textarea>
                        @elseif (str_ends_with($field, '_at'))
                            <input wire:model="form.{{ $field }}" type="datetime-local">
                        @elseif (str_contains($field, 'date') || str_ends_with($field, '_on'))
                            <input wire:model="form.{{ $field }}" type="date">
                        @elseif ($field === 'password')
                            <input wire:model="form.password" type="password" autocomplete="new-password">
                        @elseif (in_array($field, ['year', 'interval_km', 'interval_days', 'soon_km', 'soon_days', 'last_service_odometer', 'next_due_odometer', 'odometer', 'detected_odometer', 'downtime_minutes', 'warranty_until_odometer', 'warning_days', 'subtotal', 'tax_amount', 'total_amount', 'net_amount']))
                            <input wire:model.live.debounce.300ms="form.{{ $field }}" type="number" step="{{ $field === 'year' || str_contains($field, 'days') ? '1' : '0.01' }}" {{ in_array($field, ['subtotal', 'total_amount']) && $section === 'service-records' ? 'readonly' : '' }}>
                        @else
                            <input wire:model="form.{{ $field }}" type="text">
                        @endif
                        @error('form.'.$field)<small class="text-red-600">{{ $message }}</small>@enderror
                    </label>
                @endforeach
            </div>
            @if ($section === 'service-records')
                <div class="mt-8 border-t pt-6">
                    <div class="flex items-center justify-between"><h2 class="section-title">{{ __('app.service_items') }}</h2><button type="button" wire:click="addItem" class="btn-secondary">{{ __('app.add_item') }}</button></div>
                    <div class="mt-4 space-y-3">
                        @foreach($items as $index => $item)
                            <div class="grid gap-3 rounded-xl bg-slate-50 p-3 sm:grid-cols-12">
                                <select wire:model="items.{{ $index }}.type" class="sm:col-span-2">@foreach($this->optionValues('service_item_type') as $itemType)<option value="{{ $itemType }}">{{ __('app.item_types.'.$itemType) }}</option>@endforeach</select>
                                <input wire:model="items.{{ $index }}.description" class="sm:col-span-4" placeholder="{{ __('app.fields.description') }}">
                                <input wire:model="items.{{ $index }}.unit" class="sm:col-span-1" placeholder="{{ __('app.fields.unit') }}">
                                <input wire:model.live.debounce.300ms="items.{{ $index }}.quantity" type="number" step="0.01" class="sm:col-span-1" placeholder="{{ __('app.quantity') }}">
                                <input wire:model.live.debounce.300ms="items.{{ $index }}.unit_price" type="number" step="0.01" class="sm:col-span-1" placeholder="{{ __('app.price') }}">
                                <input wire:model.live.debounce.300ms="items.{{ $index }}.tax_rate" type="number" step="0.01" class="sm:col-span-1" placeholder="%">
                                <input wire:model="items.{{ $index }}.total_amount" type="number" step="0.01" readonly class="sm:col-span-1">
                                <button type="button" wire:click="removeItem({{ $index }})" class="text-red-600 sm:col-span-1">×</button>
                            </div>
                            @error('items.'.$index.'.description')<small class="text-red-600">{{ $message }}</small>@enderror
                        @endforeach
                    </div>
                </div>
            @endif
            <div class="mt-8 flex justify-end gap-3"><a href="{{ route($section.'.index') }}" wire:navigate class="btn-secondary">{{ __('app.cancel') }}</a><button class="btn-primary">{{ __('app.save') }}</button></div>
        </form>
        @if($recordId && in_array($section, ['vehicles', 'documents', 'defects', 'service-records', 'expenses']))
            <form wire:submit="uploadFile" class="card mt-5 flex max-w-4xl flex-col gap-3 sm:flex-row">
                <input wire:model="upload" type="file"><button class="btn-primary">{{ __('app.upload') }}</button>
            </form>
        @endif
    @else
        @if ($section === 'vehicles')
            <div class="mb-5 overflow-x-auto"><nav class="flex min-w-max gap-1 rounded-xl bg-white p-1 shadow-sm">
                @foreach(['overview', 'service', 'defects', 'expenses', 'odometer', 'documents', 'files'] as $tab)
                    <button wire:click="$set('activeTab','{{ $tab }}')" class="rounded-lg px-4 py-2 text-sm font-semibold {{ $activeTab === $tab ? 'bg-blue-700 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ __('app.tabs.'.$tab) }}</button>
                @endforeach
            </nav></div>
        @endif
        <section class="card max-w-5xl {{ $section === 'defects' && $this->value($record->severity) === 'critical' ? '!border-red-400 ring-2 ring-red-100' : '' }}">
            <div class="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-center sm:justify-between">
                <div><span class="status-badge">{{ __('app.statuses.'.$this->value($record->status ?? $record->role ?? 'active')) }}</span><h2 class="mt-2 text-2xl font-bold">{{ $this->displayName($record) }}</h2></div>
                <div class="flex gap-2">
                    @can('update', $record)
                        @if($section === 'service-records' && $this->value($record->status) !== 'completed')<button wire:click="completeService" wire:confirm="{{ __('app.confirm_complete') }}" class="btn-primary">{{ __('app.complete') }}</button>@endif
                        <a href="{{ route($section.'.edit', $recordId) }}" wire:navigate class="btn-secondary">{{ __('app.edit') }}</a>
                    @endcan
                </div>
            </div>
            @if($section === 'vehicles' && $activeTab === 'odometer')
                @can('update', $record)
                    <form wire:submit="addOdometer" class="mt-6 grid max-w-2xl gap-3 sm:grid-cols-2">
                        <input wire:model="form.odometer" type="number" step="0.1" min="0" placeholder="{{ __('app.odometer') }}">
                        <input wire:model="form.odometer_notes" placeholder="{{ __('app.fields.notes') }}">
                        @if(auth()->user()->isAdmin())
                            <label class="inline-flex items-center gap-2 text-sm font-semibold"><input wire:model.live="form.odometer_override" type="checkbox" class="rounded border-slate-300"> {{ __('app.odometer_override') }}</label>
                            @if($form['odometer_override'] ?? false)<input wire:model="form.odometer_override_reason" placeholder="{{ __('app.override_reason') }}">@endif
                        @endif
                        <button class="btn-primary sm:col-span-2 sm:justify-center">{{ __('app.add') }}</button>
                    </form>
                @endcan
            @elseif($section === 'vehicles' && $activeTab === 'files')
                @can('update', $record)
                <form wire:submit="uploadFile" class="mt-6 flex flex-col gap-3 sm:flex-row"><input wire:model="upload" type="file"><button class="btn-primary">{{ __('app.upload') }}</button></form>
                @endcan
            @elseif($section !== 'vehicles' || $activeTab === 'overview')
                <dl class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach(($record?->getAttributes() ?? []) as $key => $value)
                        @continue(in_array($key, ['id', 'password', 'remember_token', 'created_at', 'updated_at', 'deleted_at']))
                        <div class="rounded-xl bg-slate-50 p-4"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('app.fields.'.$key) }}</dt><dd class="mt-1 break-words font-medium text-slate-900">{{ str_ends_with($key, '_at') || str_ends_with($key, '_on') || str_ends_with($key, '_date') ? $this->formatDate($value, str_ends_with($key, '_at')) : $this->value($value) }}</dd></div>
                    @endforeach
                </dl>
            @endif
            @if(in_array($section, ['documents', 'defects', 'service-records', 'expenses']))
                @can('update', $record)
                    <form wire:submit="uploadFile" class="mt-6 flex flex-col gap-3 border-t pt-6 sm:flex-row"><input wire:model="upload" type="file"><button class="btn-primary">{{ __('app.upload') }}</button></form>
                @endcan
            @endif
            @if(($section === 'vehicles' && $activeTab !== 'overview') || $related->isNotEmpty())
                <div class="mt-6 divide-y rounded-xl border">
                    @forelse($related as $relatedItem)
                        <article class="flex flex-col gap-1 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <strong>
                                @if($relatedItem instanceof App\Models\Attachment)
                                    <a class="link" href="{{ route('attachments.download', $relatedItem) }}">{{ $relatedItem->original_name }}</a>
                                @elseif($section === 'vehicles' && in_array($activeTab, ['service','defects','expenses','documents']))
                                    @php($relatedRoute = ['service' => 'service-records.show', 'defects' => 'defects.show', 'expenses' => 'expenses.show', 'documents' => 'documents.show'][$activeTab])
                                    <a href="{{ route($relatedRoute, $relatedItem) }}" wire:navigate class="link">{{ $this->displayName($relatedItem) }}</a>
                                @else
                                    {{ $relatedItem->title ?? $relatedItem->description ?? $relatedItem->name ?? $relatedItem->provider_name ?? $relatedItem->reading ?? (($this->value($relatedItem->from_status ?? '')) . ' → ' . ($this->value($relatedItem->to_status ?? ''))) ?: ('#'.$relatedItem->id) }}
                                @endif
                            </strong>
                            <span class="text-sm text-slate-500">{{ $this->value($relatedItem->status ?? $relatedItem->incurred_on ?? $relatedItem->recorded_at ?? $relatedItem->created_at) }}</span>
                        </article>
                    @empty <div class="empty-state m-4">{{ __('app.no_records') }}</div> @endforelse
                </div>
            @endif
            @if($attachments->isNotEmpty() && !($section === 'vehicles' && $activeTab === 'files'))
                <div class="mt-6">
                    <h3 class="font-bold">{{ __('app.files') }}</h3>
                    <div class="mt-2 divide-y rounded-xl border">@foreach($attachments as $attachment)<a class="link block p-3" href="{{ route('attachments.download', $attachment) }}">{{ $attachment->original_name }}</a>@endforeach</div>
                </div>
            @endif
        </section>
    @endif
</div>
