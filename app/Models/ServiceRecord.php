<?php

namespace App\Models;

use App\Enums\ServiceStatus;
use App\Enums\ServiceType;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'vehicle_id', 'maintenance_plan_id', 'defect_id', 'created_by', 'status',
    'service_type', 'provider_name', 'reference_number', 'planned_at', 'started_at',
    'completed_at', 'downtime_minutes', 'warranty_until_date',
    'warranty_until_odometer', 'odometer', 'currency', 'subtotal', 'tax_amount',
    'total_amount', 'description', 'internal_notes', 'notes',
])]
class ServiceRecord extends Model
{
    use HasAttachments, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => ServiceStatus::class,
            'service_type' => ServiceType::class,
            'planned_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'downtime_minutes' => 'integer',
            'warranty_until_date' => 'immutable_date',
            'warranty_until_odometer' => 'decimal:1',
            'odometer' => 'decimal:1',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(VehicleMaintenancePlan::class, 'maintenance_plan_id');
    }

    public function defect(): BelongsTo
    {
        return $this->belongsTo(Defect::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ServiceRecordItem::class);
    }

    public function expense(): HasOne
    {
        return $this->hasOne(Expense::class);
    }
}
