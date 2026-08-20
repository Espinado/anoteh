<?php

namespace App\Livewire;

use App\Actions\ChangeDefectStatus;
use App\Actions\CompleteServiceRecord;
use App\Actions\CreateMaintenancePlan;
use App\Actions\RecordOdometerReading;
use App\Enums\DefectSeverity;
use App\Enums\DefectStatus;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\ExpenseCategory;
use App\Enums\FuelType;
use App\Enums\MaintenanceCategory;
use App\Enums\MaintenancePlanStatus;
use App\Enums\ServiceItemType;
use App\Enums\ServiceStatus;
use App\Enums\ServiceType;
use App\Enums\UserRole;
use App\Enums\VehicleCategory;
use App\Enums\VehicleStatus;
use App\Models\AuditLog;
use App\Models\Defect;
use App\Models\Expense;
use App\Models\MaintenanceTemplate;
use App\Models\ServiceRecord;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Models\VehicleMaintenancePlan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class AdminUi extends Component
{
    use AuthorizesRequests, WithFileUploads, WithPagination;

    public string $section = 'dashboard';

    public string $mode = 'index';

    public ?int $recordId = null;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $category = '';

    #[Url]
    public string $sort = 'created_at';

    #[Url]
    public string $direction = 'desc';

    #[Url]
    public bool $trash = false;

    public string $activeTab = 'overview';

    public array $form = [];

    public array $items = [];

    public $upload;

    private const MODELS = [
        'vehicles' => Vehicle::class,
        'templates' => MaintenanceTemplate::class,
        'plans' => VehicleMaintenancePlan::class,
        'defects' => Defect::class,
        'service-records' => ServiceRecord::class,
        'expenses' => Expense::class,
        'documents' => VehicleDocument::class,
        'users' => User::class,
        'audit' => AuditLog::class,
    ];

    public function mount(string $section = 'dashboard', string $mode = 'index', ?int $recordId = null): void
    {
        $this->section = $section;
        $this->mode = $mode;
        $this->recordId = $recordId;
        $this->form = $this->defaults();
        if (in_array($section, ['audit', 'users'], true)) {
            abort_unless(Auth::user()->isAdmin(), 403);
        }
        if ($this->trash && ! Auth::user()->isAdmin()) {
            $this->trash = false;
        }

        if ($mode === 'create' && $this->hasModel()) {
            $this->authorize('create', $this->modelClass());
        }

        if ($recordId && $this->hasModel()) {
            $record = $this->record();
            $this->authorize($mode === 'edit' ? 'update' : 'view', $record);
            $this->form = array_merge($this->form, $this->editableValues($record));
            if ($record instanceof ServiceRecord) {
                $this->items = $record->items()->get()->map(fn ($item) => array_merge(
                    ['unit' => 'pcs', 'tax_rate' => 0, 'net_amount' => 0, 'tax_amount' => 0],
                    $item->only(['type', 'description', 'unit', 'quantity', 'unit_price', 'tax_rate', 'net_amount', 'tax_amount', 'total_amount']),
                ))->all();
            }
        }

        if ($section === 'service-records' && $mode === 'create') {
            $this->addItem();
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingCategory(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        abort_unless(in_array($column, $this->sortableColumns(), true), 422);
        $this->direction = $this->sort === $column && $this->direction === 'asc' ? 'desc' : 'asc';
        $this->sort = $column;
        $this->resetPage();
    }

    public function setDashboardFilter(string $filter): void
    {
        $routes = [
            'vehicles' => ['vehicles.index', []],
            'active_vehicles' => ['vehicles.index', ['status' => 'active']],
            'in_repair' => ['service-records.index', ['status' => 'in_progress']],
            'overdue' => ['plans.index', ['status' => 'overdue']],
            'soon' => ['plans.index', ['status' => 'soon']],
            'open_defects' => ['defects.index', ['status' => 'open']],
            'critical_defects' => ['defects.index', ['category' => 'critical']],
            'expiring_documents' => ['documents.index', ['status' => 'expiring_soon']],
            'costs_month' => ['reports.index', ['type' => 'expenses', 'from' => now()->startOfMonth()->toDateString(), 'to' => now()->endOfMonth()->toDateString()]],
            'costs_year' => ['reports.index', ['type' => 'expenses', 'from' => now()->startOfYear()->toDateString(), 'to' => now()->endOfYear()->toDateString()]],
        ];

        if (isset($routes[$filter])) {
            $this->redirectRoute($routes[$filter][0], $routes[$filter][1], navigate: true);
        }
    }

    public function addItem(): void
    {
        $this->items[] = [
            'type' => 'part', 'description' => '', 'unit' => 'pcs', 'quantity' => 1,
            'unit_price' => 0, 'tax_rate' => 21, 'net_amount' => 0, 'tax_amount' => 0, 'total_amount' => 0,
        ];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
        $this->recalculateServiceTotals();
    }

    public function updatedItems(): void
    {
        $this->recalculateServiceTotals();
    }

    public function updatedFormTaxAmount(): void
    {
        if ($this->section === 'service-records') {
            $this->recalculateServiceTotals();
        } elseif ($this->section === 'expenses') {
            $this->recalculateExpenseTotals();
        }
    }

    public function updatedFormNetAmount(): void
    {
        $this->recalculateExpenseTotals();
    }

    public function save(): void
    {
        if ($this->section === 'plans' && ! $this->recordId) {
            $validated = $this->validate($this->rules())['form'];
            $this->authorize('create', VehicleMaintenancePlan::class);
            $plan = app(CreateMaintenancePlan::class)->execute(
                Vehicle::findOrFail($validated['vehicle_id']),
                MaintenanceTemplate::findOrFail($validated['maintenance_template_id']),
                Auth::user(),
                filled($validated['interval_km'] ?? null) ? (float) $validated['interval_km'] : null,
                filled($validated['interval_days'] ?? null) ? (int) $validated['interval_days'] : null,
                filled($validated['starts_on'] ?? null) ? CarbonImmutable::parse($validated['starts_on'], 'UTC')->startOfDay() : null,
            );
            session()->flash('success', __('app.saved'));
            $this->redirectRoute('plans.show', $plan, navigate: true);

            return;
        }

        $class = $this->modelClass();
        $record = $this->recordId ? $this->record() : new $class;
        $this->authorize($this->recordId ? 'update' : 'create', $this->recordId ? $record : $class);

        $validated = $this->validate($this->rules());
        $data = $validated['form'];
        $requestedDefectStatus = $record instanceof Defect ? DefectStatus::from($data['status']) : null;
        $requestedServiceStatus = $record instanceof ServiceRecord ? ServiceStatus::tryFrom($data['status']) : null;
        if ($record instanceof Defect) {
            unset($data['status']);
            if (! $record->exists) {
                $data['reported_by'] = Auth::id();
            }
        }
        if ($record instanceof ServiceRecord) {
            $this->recalculateServiceTotals();
            $data = $this->form;
            if ($data['status'] === 'scheduled' && ! in_array('scheduled', $this->optionValues('service_status'), true)) {
                $data['status'] = ServiceStatus::Draft->value;
            }
            if ($requestedServiceStatus === ServiceStatus::Completed && $record->status !== ServiceStatus::Completed) {
                $data['status'] = ServiceStatus::InProgress->value;
            }
            if (! $record->exists) {
                $data['created_by'] = Auth::id();
            }
        }
        if ($record instanceof Expense && ! $record->exists) {
            $data['created_by'] = Auth::id();
        }
        if ($record instanceof User) {
            if (filled($data['password'] ?? null)) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }
        }
        if ($record instanceof MaintenanceTemplate && is_string($data['recommended_operations'] ?? null)) {
            $data['recommended_operations'] = array_values(array_filter(array_map('trim', preg_split('/\R/', $data['recommended_operations']))));
        }
        $data = array_map(fn ($value) => $value === '' ? null : $value, $data);
        $data = array_intersect_key($data, array_flip(Schema::getColumnListing($record->getTable())));

        DB::transaction(function () use ($record, $data, $requestedDefectStatus, $requestedServiceStatus): void {
            $record->fill($data)->save();

            if ($record instanceof Defect && $requestedDefectStatus !== null && $record->status !== $requestedDefectStatus) {
                app(ChangeDefectStatus::class)->execute($record, $requestedDefectStatus, Auth::user(), __('app.status_changed_from_ui'));
            }

            if ($record instanceof ServiceRecord) {
                $record->items()->delete();
                $record->items()->createMany(collect($this->items)
                    ->filter(fn ($item) => filled($item['description'] ?? null))
                    ->map(function ($item) {
                        if (! Schema::hasColumn('service_record_items', 'tax_rate')) {
                            $item['total_amount'] = $item['net_amount'];
                        }
                        $item = array_intersect_key($item, array_flip(Schema::getColumnListing('service_record_items')));

                        return $item;
                    })->all());
                if ($requestedServiceStatus === ServiceStatus::Completed && $record->status !== ServiceStatus::Completed) {
                    app(CompleteServiceRecord::class)->execute($record, Auth::user());
                }
            }
        });

        session()->flash('success', __('app.saved'));
        $this->redirectRoute($this->section.'.show', $record, navigate: true);
    }

    public function delete(int $id): void
    {
        $record = $this->modelClass()::findOrFail($id);
        $this->authorize('delete', $record);
        $record->delete();
        session()->flash('success', __('app.deleted'));
    }

    public function restore(int $id): void
    {
        abort_unless(Auth::user()->isAdmin(), 403);
        $class = $this->modelClass();
        abort_unless(in_array(SoftDeletes::class, class_uses_recursive($class), true), 422);
        $record = $class::withTrashed()->findOrFail($id);
        $this->authorize('restore', $record);
        $record->restore();
        session()->flash('success', __('app.restored'));
    }

    public function addOdometer(RecordOdometerReading $action): void
    {
        $validated = $this->validate([
            'form.odometer' => ['required', 'numeric', 'min:0'],
            'form.odometer_notes' => ['nullable', 'string', 'max:1000'],
            'form.odometer_override' => ['nullable'],
            'form.odometer_override_reason' => [
                Rule::requiredIf(fn () => (bool) ($this->form['odometer_override'] ?? false)),
                'nullable', 'string', 'max:1000',
            ],
        ]);
        $vehicle = Vehicle::findOrFail($this->recordId);
        $this->authorize('update', $vehicle);
        $override = Auth::user()->isAdmin() && (bool) ($validated['form']['odometer_override'] ?? false);
        $action->execute(
            $vehicle,
            (float) $validated['form']['odometer'],
            Auth::user(),
            notes: $validated['form']['odometer_notes'] ?: null,
            adminOverride: $override,
            overrideReason: $override ? $validated['form']['odometer_override_reason'] : null,
        );
        $this->form['odometer'] = '';
        $this->form['odometer_notes'] = '';
        $this->form['odometer_override'] = false;
        $this->form['odometer_override_reason'] = '';
        session()->flash('success', __('app.odometer_saved'));
    }

    public function completeService(CompleteServiceRecord $action): void
    {
        $record = ServiceRecord::findOrFail($this->recordId);
        $this->authorize('update', $record);
        $action->execute($record, Auth::user());
        session()->flash('success', __('app.service_completed'));
    }

    public function markNotificationRead(string $id): void
    {
        Auth::user()->notifications()->findOrFail($id)->markAsRead();
    }

    public function markAllNotificationsRead(): void
    {
        Auth::user()->unreadNotifications->markAsRead();
    }

    public function uploadFile(): void
    {
        $maxKb = (int) config('attachments.max_kb', 10240);
        $mimes = config('attachments.mimes', ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx']);
        $this->validate([
            'upload' => [
                'required', 'file', 'max:'.$maxKb,
                'mimes:'.implode(',', $mimes),
            ],
        ]);
        $record = $this->record();
        $this->authorize('update', $record);
        abort_unless(method_exists($record, 'attachments'), 422);

        $disk = config('attachments.private_disks.0', 'local');
        abort_unless(in_array($disk, config('attachments.private_disks', []), true), 500);
        $extension = strtolower($this->upload->getClientOriginalExtension());
        $filename = Str::uuid().($extension ? '.'.$extension : '');
        $directory = 'attachments/'.Str::snake(class_basename($record)).'/'.$record->getKey();
        $checksum = hash_file('sha256', $this->upload->getRealPath());
        $path = $this->upload->storeAs($directory, $filename, $disk);
        abort_unless($path, 500);

        $record->attachments()->create([
            'uploaded_by' => Auth::id(),
            'disk' => $disk,
            'path' => $path,
            'original_name' => basename($this->upload->getClientOriginalName()),
            'mime_type' => $this->upload->getMimeType(),
            'size' => $this->upload->getSize(),
            'sha256' => $checksum,
        ]);
        $this->reset('upload');
        session()->flash('success', __('app.file_uploaded'));
    }

    public function optionValues(string $field): array
    {
        $enum = match ($field) {
            'vehicle_status' => VehicleStatus::class,
            'category_template' => MaintenanceCategory::class,
            'plan_status' => MaintenancePlanStatus::class,
            'severity' => DefectSeverity::class,
            'defect_status' => DefectStatus::class,
            'service_status' => ServiceStatus::class,
            'service_type' => ServiceType::class,
            'service_item_type' => ServiceItemType::class,
            'expense_category' => ExpenseCategory::class,
            'vehicle_category' => VehicleCategory::class,
            'fuel_type' => FuelType::class,
            'document_type' => DocumentType::class,
            'document_status' => DocumentStatus::class,
            'role' => UserRole::class,
            default => null,
        };

        return $enum ? array_column($enum::cases(), 'value') : [];
    }

    public function defectStatusOptions(): array
    {
        if ($this->mode === 'index') {
            return $this->optionValues('defect_status');
        }
        if (! $this->recordId) {
            return [DefectStatus::Open->value];
        }
        $current = $this->record()->status;

        return array_unique([$current->value, ...match ($current) {
            DefectStatus::Open => [DefectStatus::Confirmed->value, DefectStatus::Deferred->value, DefectStatus::Rejected->value],
            DefectStatus::Confirmed => [DefectStatus::InProgress->value, DefectStatus::InRepair->value, DefectStatus::Deferred->value, DefectStatus::Rejected->value],
            DefectStatus::InProgress => [DefectStatus::InRepair->value, DefectStatus::Resolved->value, DefectStatus::Deferred->value],
            DefectStatus::InRepair => [DefectStatus::Resolved->value, DefectStatus::Deferred->value],
            DefectStatus::Deferred => [DefectStatus::Confirmed->value, DefectStatus::InProgress->value, DefectStatus::Rejected->value],
            DefectStatus::Resolved, DefectStatus::Rejected => [],
        }]);
    }

    public function serviceStatusOptions(): array
    {
        if ($this->mode === 'index') {
            return $this->optionValues('service_status');
        }
        if (! $this->recordId) {
            return [ServiceStatus::Scheduled->value, ServiceStatus::Draft->value, ServiceStatus::InProgress->value];
        }
        $current = $this->record()->status;

        return array_unique([$current->value, ...match ($current) {
            ServiceStatus::Scheduled => [ServiceStatus::Draft->value, ServiceStatus::InProgress->value, ServiceStatus::Cancelled->value],
            ServiceStatus::Draft => [ServiceStatus::Scheduled->value, ServiceStatus::InProgress->value, ServiceStatus::Cancelled->value],
            ServiceStatus::InProgress => [ServiceStatus::Completed->value, ServiceStatus::Cancelled->value],
            ServiceStatus::Completed, ServiceStatus::Cancelled => [],
        }]);
    }

    public function value(mixed $value): string
    {
        return $value instanceof \BackedEnum ? $value->value : (string) ($value ?? '');
    }

    public function formatDate(mixed $value, bool $withTime = true): string
    {
        if (blank($value)) {
            return '—';
        }

        return Carbon::parse($value)
            ->timezone('Europe/Riga')
            ->format($withTime ? 'd.m.Y H:i' : 'd.m.Y');
    }

    public function displayName(Model $record): string
    {
        return (string) ($record->registration_number ?? $record->name ?? $record->title ?? $record->number
            ?? $record->provider_name ?? $record->vendor ?? $record->event ?? '#'.$record->getKey());
    }

    public function displayDetails(Model $record): string
    {
        return collect([
            $record->make ?? null, $record->model ?? null, $record->email ?? null,
            $record->template?->name ?? null, $record->vehicle?->registration_number ?? null,
            $record->reference_number ?? null, $record->notes ?? $record->description ?? null,
            $record->actor?->name ?? null, $record->auditable_type ?? null, $record->ip_address ?? null,
        ])->filter()->map(fn ($value) => $this->value($value))->join(' · ');
    }

    protected function defaults(): array
    {
        return match ($this->section) {
            'vehicles' => [
                'registration_number' => '', 'vin' => '', 'make' => '', 'model' => '', 'year' => '',
                'category' => 'truck', 'body_type' => '', 'fuel_type' => 'diesel', 'commissioned_on' => '',
                'responsible_user_id' => '', 'primary_attachment_id' => '', 'status' => 'active', 'notes' => '',
                'odometer' => '', 'odometer_notes' => '', 'odometer_override' => false, 'odometer_override_reason' => '',
            ],
            'templates' => ['name' => '', 'category' => 'other', 'interval_km' => '', 'interval_days' => '', 'soon_km' => 1000, 'soon_days' => 30, 'recommended_operations' => '', 'description' => '', 'active' => true],
            'plans' => ['vehicle_id' => '', 'maintenance_template_id' => '', 'interval_km' => '', 'interval_days' => '', 'starts_on' => now('Europe/Riga')->format('Y-m-d'), 'last_service_odometer' => '', 'last_service_date' => '', 'next_due_odometer' => '', 'next_due_date' => '', 'status' => 'scheduled', 'active' => true],
            'defects' => ['vehicle_id' => '', 'assigned_to' => '', 'title' => '', 'description' => '', 'category' => 'other', 'severity' => 'medium', 'status' => 'open', 'detected_odometer' => '', 'reported_at' => now()->format('Y-m-d\TH:i')],
            'service-records' => [
                'vehicle_id' => '', 'maintenance_plan_id' => '', 'defect_id' => '', 'service_type' => 'other',
                'status' => 'scheduled', 'provider_name' => '', 'reference_number' => '',
                'planned_at' => now()->format('Y-m-d\TH:i'), 'started_at' => '', 'completed_at' => '',
                'odometer' => '', 'downtime_minutes' => 0, 'warranty_until_date' => '',
                'warranty_until_odometer' => '', 'currency' => 'EUR', 'subtotal' => 0,
                'tax_amount' => 0, 'total_amount' => 0, 'description' => '', 'internal_notes' => '', 'notes' => '',
            ],
            'expenses' => ['vehicle_id' => '', 'service_record_id' => '', 'category' => 'other', 'vendor' => '', 'reference_number' => '', 'incurred_on' => now()->format('Y-m-d'), 'currency' => 'EUR', 'net_amount' => 0, 'tax_amount' => 0, 'total_amount' => 0, 'notes' => ''],
            'documents' => ['vehicle_id' => '', 'type' => 'other', 'number' => '', 'issued_on' => '', 'expires_on' => '', 'warning_days' => 30, 'status' => 'valid', 'notes' => ''],
            'users' => ['name' => '', 'email' => '', 'role' => 'viewer', 'password' => ''],
            default => [],
        };
    }

    protected function editableValues(Model $record): array
    {
        $values = $record->only(array_keys($this->form));
        foreach ($values as $key => $value) {
            if ($value instanceof \BackedEnum) {
                $values[$key] = $value->value;
            } elseif (is_array($value)) {
                $values[$key] = implode(PHP_EOL, $value);
            } elseif ($value instanceof \DateTimeInterface) {
                $values[$key] = str_ends_with($key, '_at') ? $value->format('Y-m-d\TH:i') : $value->format('Y-m-d');
            }
        }

        return $values;
    }

    protected function rules(): array
    {
        $id = $this->recordId;
        $rules = match ($this->section) {
            'vehicles' => [
                'registration_number' => ['required', 'string', 'max:32', Rule::unique('vehicles', 'registration_number')->ignore($id)->whereNull('deleted_at')],
                'vin' => ['nullable', 'string', 'size:17', Rule::unique('vehicles', 'vin')->ignore($id)->whereNull('deleted_at')],
                'make' => ['required', 'string', 'max:80'], 'model' => ['required', 'string', 'max:80'],
                'year' => ['nullable', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
                'category' => ['required', Rule::enum(VehicleCategory::class)], 'body_type' => ['nullable', 'string', 'max:48'],
                'fuel_type' => ['required', Rule::enum(FuelType::class)], 'commissioned_on' => ['nullable', 'date'],
                'responsible_user_id' => ['nullable', Rule::exists('users', 'id')->whereNull('deleted_at')],
                'primary_attachment_id' => ['nullable', 'integer', 'exists:attachments,id'],
                'status' => ['required', Rule::enum(VehicleStatus::class)], 'notes' => ['nullable', 'string'],
            ],
            'templates' => [
                'name' => ['required', 'string', 'max:160'], 'category' => ['required', Rule::enum(MaintenanceCategory::class)],
                'interval_km' => ['nullable', 'numeric', 'min:1', 'required_without:form.interval_days'],
                'interval_days' => ['nullable', 'integer', 'min:1', 'required_without:form.interval_km'],
                'soon_km' => ['required', 'numeric', 'min:0'], 'soon_days' => ['required', 'integer', 'min:0'],
                'recommended_operations' => ['nullable', 'string'], 'description' => ['nullable', 'string'], 'active' => ['boolean'],
            ],
            'plans' => [
                'vehicle_id' => ['required', Rule::exists('vehicles', 'id')->whereNull('deleted_at')],
                'maintenance_template_id' => ['required', Rule::exists('maintenance_templates', 'id')->whereNull('deleted_at'), Rule::unique('vehicle_maintenance_plans', 'maintenance_template_id')->where(fn ($query) => $query->where('vehicle_id', $this->form['vehicle_id']))->ignore($id)->whereNull('deleted_at')],
                'interval_km' => ['nullable', 'numeric', 'min:1'], 'interval_days' => ['nullable', 'integer', 'min:1'],
                'starts_on' => ['nullable', 'date'],
                'last_service_odometer' => ['nullable', 'numeric', 'min:0'], 'last_service_date' => ['nullable', 'date'],
                'next_due_odometer' => ['nullable', 'numeric', 'min:0', 'required_without:form.next_due_date'],
                'next_due_date' => ['nullable', 'date', 'required_without:form.next_due_odometer'],
                'status' => ['required', Rule::enum(MaintenancePlanStatus::class)], 'active' => ['boolean'],
            ],
            'defects' => [
                'vehicle_id' => ['required', Rule::exists('vehicles', 'id')->whereNull('deleted_at')],
                'assigned_to' => ['nullable', Rule::exists('users', 'id')->whereNull('deleted_at')],
                'title' => ['required', 'string', 'max:180'], 'description' => ['required', 'string'],
                'category' => ['required', 'string', 'max:32'], 'severity' => ['required', Rule::enum(DefectSeverity::class)],
                'status' => ['required', Rule::in($this->defectStatusOptions())], 'detected_odometer' => ['nullable', 'numeric', 'min:0'],
                'reported_at' => ['required', 'date'],
            ],
            'service-records' => [
                'vehicle_id' => ['required', Rule::exists('vehicles', 'id')->whereNull('deleted_at')],
                'maintenance_plan_id' => ['nullable', Rule::exists('vehicle_maintenance_plans', 'id')->whereNull('deleted_at')],
                'defect_id' => ['nullable', Rule::exists('defects', 'id')->whereNull('deleted_at')],
                'service_type' => ['required', Rule::enum(ServiceType::class)],
                'status' => ['required', Rule::in($this->serviceStatusOptions())],
                'provider_name' => ['nullable', 'string', 'max:160'],
                'reference_number' => ['nullable', 'string', 'max:80'], 'planned_at' => ['nullable', 'date'],
                'started_at' => ['nullable', 'date'],
                'completed_at' => ['nullable', 'date', 'after_or_equal:form.started_at'], 'odometer' => ['required', 'numeric', 'min:0'],
                'downtime_minutes' => ['nullable', 'integer', 'min:0'], 'warranty_until_date' => ['nullable', 'date'],
                'warranty_until_odometer' => ['nullable', 'numeric', 'min:0'],
                'currency' => ['required', 'string', 'size:3'], 'subtotal' => ['required', 'numeric', 'min:0'],
                'tax_amount' => ['required', 'numeric', 'min:0'], 'total_amount' => ['required', 'numeric', 'min:0'],
                'description' => ['nullable', 'string'], 'internal_notes' => ['nullable', 'string'], 'notes' => ['nullable', 'string'],
            ],
            'expenses' => [
                'vehicle_id' => ['nullable', Rule::exists('vehicles', 'id')->whereNull('deleted_at')],
                'service_record_id' => ['nullable', Rule::exists('service_records', 'id')->whereNull('deleted_at'), Rule::unique('expenses', 'service_record_id')->ignore($id)->whereNull('deleted_at')],
                'category' => ['required', Rule::enum(ExpenseCategory::class)], 'vendor' => ['nullable', 'string', 'max:160'],
                'reference_number' => ['nullable', 'string', 'max:80'], 'incurred_on' => ['required', 'date'],
                'currency' => ['required', 'string', 'size:3'], 'net_amount' => ['required', 'numeric', 'min:0'],
                'tax_amount' => ['required', 'numeric', 'min:0'], 'total_amount' => ['required', 'numeric', 'min:0'],
                'notes' => ['nullable', 'string'],
            ],
            'documents' => [
                'vehicle_id' => ['required', Rule::exists('vehicles', 'id')->whereNull('deleted_at')],
                'type' => ['required', Rule::enum(DocumentType::class)], 'number' => ['nullable', 'string', 'max:100'],
                'issued_on' => ['nullable', 'date'], 'expires_on' => ['nullable', 'date', 'after_or_equal:form.issued_on'],
                'warning_days' => ['required', 'integer', 'min:0', 'max:3650'],
                'status' => ['required', Rule::enum(DocumentStatus::class)], 'notes' => ['nullable', 'string'],
            ],
            'users' => [
                'name' => ['required', 'string', 'max:100'],
                'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($id)->whereNull('deleted_at')],
                'role' => ['required', Rule::enum(UserRole::class)], 'password' => [$id ? 'nullable' : 'required', 'string', 'min:8'],
            ],
            default => [],
        };
        if ($this->section === 'plans') {
            if ($this->recordId) {
                unset($rules['starts_on']);
            } else {
                foreach (['last_service_odometer', 'last_service_date', 'next_due_odometer', 'next_due_date', 'status', 'active'] as $field) {
                    unset($rules[$field]);
                }
                $rules['starts_on'] = ['required', 'date'];
            }
        }

        $result = [];
        foreach ($rules as $key => $value) {
            $result['form.'.$key] = $value;
        }
        if ($this->section === 'service-records') {
            $result += [
                'items' => ['array'], 'items.*.type' => ['required', Rule::enum(ServiceItemType::class)],
                'items.*.description' => ['required', 'string', 'max:255'], 'items.*.quantity' => ['required', 'numeric', 'gt:0'],
                'items.*.unit' => ['required', 'string', 'max:24'], 'items.*.unit_price' => ['required', 'numeric', 'min:0'],
                'items.*.tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
                'items.*.net_amount' => ['required', 'numeric', 'min:0'], 'items.*.tax_amount' => ['required', 'numeric', 'min:0'],
                'items.*.total_amount' => ['required', 'numeric', 'min:0'],
            ];
        }

        return $result;
    }

    protected function recalculateServiceTotals(): void
    {
        if ($this->section !== 'service-records') {
            return;
        }
        foreach ($this->items as &$item) {
            $item['net_amount'] = round((float) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0), 2);
            $item['tax_amount'] = round($item['net_amount'] * (float) ($item['tax_rate'] ?? 0) / 100, 2);
            $item['total_amount'] = round($item['net_amount'] + $item['tax_amount'], 2);
        }
        unset($item);
        $this->form['subtotal'] = round(array_sum(array_column($this->items, 'net_amount')), 2);
        $this->form['tax_amount'] = round(array_sum(array_column($this->items, 'tax_amount')), 2);
        $this->form['total_amount'] = round(array_sum(array_column($this->items, 'total_amount')), 2);
    }

    protected function recalculateExpenseTotals(): void
    {
        if ($this->section === 'expenses') {
            $this->form['total_amount'] = round((float) ($this->form['net_amount'] ?? 0) + (float) ($this->form['tax_amount'] ?? 0), 2);
        }
    }

    protected function records()
    {
        $class = $this->modelClass();
        $this->authorize('viewAny', $class);
        $searchColumns = match ($this->section) {
            'vehicles' => ['registration_number', 'make', 'model', 'vin'],
            'templates' => ['name', 'description'], 'defects' => ['title', 'description'],
            'service-records' => ['provider_name', 'reference_number', 'notes'],
            'expenses' => ['vendor', 'reference_number', 'notes'], 'documents' => ['number', 'notes'],
            'users' => ['name', 'email'], 'audit' => ['event', 'ip_address'], default => [],
        };
        $with = match ($this->section) {
            'plans' => ['vehicle', 'template'], 'defects', 'service-records', 'expenses', 'documents' => ['vehicle'], default => [],
        };
        if ($this->section === 'audit') {
            $with = ['actor'];
        }
        $query = $class::query()->with($with);
        if ($this->trash && Auth::user()->isAdmin() && in_array(SoftDeletes::class, class_uses_recursive($class), true)) {
            $query->onlyTrashed();
        }

        return $query
            ->when($this->search && $searchColumns, fn ($query) => $query->where(function ($query) use ($searchColumns) {
                foreach ($searchColumns as $column) {
                    $query->orWhere($column, 'like', '%'.$this->search.'%');
                }
            }))
            ->when($this->status, fn ($query) => $query->where('status', $this->status))
            ->when($this->category, function ($query) {
                $column = $this->section === 'defects' && $this->category === 'critical' ? 'severity' : 'category';
                $query->where($column, $this->category);
            })
            ->orderBy(in_array($this->sort, $this->sortableColumns(), true) ? $this->sort : 'created_at', $this->direction === 'asc' ? 'asc' : 'desc')
            ->paginate(15);
    }

    protected function sortableColumns(): array
    {
        return match ($this->section) {
            'vehicles' => ['registration_number', 'make', 'year', 'status', 'category', 'created_at'],
            'templates' => ['name', 'category', 'active', 'created_at'],
            'plans' => ['status', 'next_due_date', 'next_due_odometer', 'created_at'],
            'defects' => ['title', 'severity', 'status', 'reported_at', 'created_at'],
            'service-records' => ['planned_at', 'started_at', 'completed_at', 'status', 'total_amount', 'created_at'],
            'expenses' => ['incurred_on', 'category', 'total_amount', 'created_at'],
            'documents' => ['type', 'status', 'expires_on', 'created_at'],
            'users' => ['name', 'email', 'role', 'created_at'],
            'audit' => ['event', 'created_at', 'actor_id'],
            default => ['created_at'],
        };
    }

    protected function dashboard(): array
    {
        foreach ([Vehicle::class, VehicleMaintenancePlan::class, Defect::class, VehicleDocument::class, ServiceRecord::class, Expense::class] as $class) {
            $this->authorize('viewAny', $class);
        }

        return [
            'active_vehicles' => Vehicle::where('status', VehicleStatus::Active)->count(),
            'in_repair' => ServiceRecord::where('status', ServiceStatus::InProgress)->distinct('vehicle_id')->count('vehicle_id'),
            'overdue' => VehicleMaintenancePlan::where('status', MaintenancePlanStatus::Overdue)->count(),
            'soon' => VehicleMaintenancePlan::where('status', MaintenancePlanStatus::Soon)->count(),
            'critical_defects' => Defect::where('severity', DefectSeverity::Critical)->whereNotIn('status', [DefectStatus::Resolved, DefectStatus::Rejected])->count(),
            'expiring_documents' => VehicleDocument::where('status', DocumentStatus::ExpiringSoon)->count(),
            'costs_month' => Expense::whereBetween('incurred_on', [now()->startOfMonth(), now()->endOfMonth()])->sum('total_amount'),
            'costs_year' => Expense::whereBetween('incurred_on', [now()->startOfYear(), now()->endOfYear()])->sum('total_amount'),
        ];
    }

    protected function relatedRecords()
    {
        if (! $this->recordId || ! $this->hasModel()) {
            return collect();
        }
        $record = $this->record();
        $relation = match ($this->section) {
            'vehicles' => ['service' => 'serviceRecords', 'defects' => 'defects', 'expenses' => 'expenses', 'odometer' => 'odometerReadings', 'documents' => 'documents', 'files' => 'attachments'][$this->activeTab] ?? null,
            'defects' => 'statusHistory', 'service-records' => 'items', 'documents' => 'attachments', default => null,
        };

        return $relation ? $record->{$relation}()->latest()->get() : collect();
    }

    protected function attachments()
    {
        if (! $this->recordId || ! $this->hasModel()) {
            return collect();
        }
        $record = $this->record();

        return method_exists($record, 'attachments') ? $record->attachments()->latest()->get() : collect();
    }

    protected function upcomingMaintenance()
    {
        $this->authorize('viewAny', VehicleMaintenancePlan::class);

        return VehicleMaintenancePlan::with(['vehicle', 'template'])
            ->whereIn('status', [MaintenancePlanStatus::Overdue, MaintenancePlanStatus::Due, MaintenancePlanStatus::Soon])
            ->orderBy('next_due_date')->limit(6)->get();
    }

    protected function record(): Model
    {
        return $this->modelClass()::findOrFail($this->recordId);
    }

    protected function hasModel(): bool
    {
        return isset(self::MODELS[$this->section]);
    }

    protected function modelClass(): string
    {
        return self::MODELS[$this->section];
    }

    protected function options(string $class, bool $needed, \Closure $query)
    {
        if (! $needed) {
            return collect();
        }
        $this->authorize('viewAny', $class);

        return $query();
    }

    protected function recentServiceEvents()
    {
        $this->authorize('viewAny', ServiceRecord::class);

        return ServiceRecord::with('vehicle')->orderByDesc('completed_at')->orderByDesc('updated_at')->limit(8)->get();
    }

    public function render()
    {
        return view('livewire.admin-ui', [
            'records' => $this->mode === 'index' && $this->hasModel() ? $this->records() : collect(),
            'record' => $this->recordId && $this->hasModel() ? $this->record() : null,
            'dashboard' => $this->section === 'dashboard' ? $this->dashboard() : [],
            'upcomingMaintenance' => $this->section === 'dashboard' ? $this->upcomingMaintenance() : collect(),
            'recentServiceEvents' => $this->section === 'dashboard' ? $this->recentServiceEvents() : collect(),
            'vehicles' => $this->options(Vehicle::class, in_array($this->section, ['plans', 'defects', 'service-records', 'expenses', 'documents', 'reports'], true), fn () => Vehicle::orderBy('registration_number')->get()),
            'templates' => $this->options(MaintenanceTemplate::class, $this->section === 'plans', fn () => MaintenanceTemplate::where('active', true)->orderBy('name')->get()),
            'plans' => $this->options(VehicleMaintenancePlan::class, $this->section === 'service-records', fn () => VehicleMaintenancePlan::with(['vehicle', 'template'])->orderBy('next_due_date')->get()),
            'defects' => $this->options(Defect::class, $this->section === 'service-records', fn () => Defect::with('vehicle')->whereNotIn('status', [DefectStatus::Resolved, DefectStatus::Rejected])->orderBy('title')->get()),
            'serviceRecords' => $this->options(ServiceRecord::class, $this->section === 'expenses', fn () => ServiceRecord::orderByDesc('started_at')->get()),
            'users' => $this->options(User::class, $this->section === 'users' || (in_array($this->section, ['vehicles', 'defects'], true) && in_array($this->mode, ['create', 'edit'], true)), fn () => User::orderBy('name')->get()),
            'notifications' => $this->section === 'notifications' ? Auth::user()->notifications()->paginate(20) : collect(),
            'related' => $this->relatedRecords(),
            'attachments' => $this->attachments(),
            'canCreate' => $this->hasModel() && $this->section !== 'audit' ? Gate::allows('create', $this->modelClass()) : false,
        ]);
    }
}
