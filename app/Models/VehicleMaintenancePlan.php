<?php

namespace App\Models;

use App\Enums\MaintenancePlanStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['vehicle_id', 'maintenance_template_id', 'interval_km', 'interval_days', 'last_service_odometer', 'last_service_date', 'next_due_odometer', 'next_due_date', 'status', 'active', 'last_reminded_at'])]
class VehicleMaintenancePlan extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['interval_km' => 'decimal:1', 'interval_days' => 'integer', 'last_service_odometer' => 'decimal:1', 'last_service_date' => 'immutable_date', 'next_due_odometer' => 'decimal:1', 'next_due_date' => 'immutable_date', 'status' => MaintenancePlanStatus::class, 'active' => 'boolean', 'last_reminded_at' => 'immutable_datetime'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MaintenanceTemplate::class, 'maintenance_template_id');
    }

    public function serviceRecords(): HasMany
    {
        return $this->hasMany(ServiceRecord::class, 'maintenance_plan_id');
    }

    public function calculateStatus(?float $odometer = null, ?CarbonImmutable $today = null): MaintenancePlanStatus
    {
        if ($this->status === MaintenancePlanStatus::Cancelled || ! $this->active) {
            return MaintenancePlanStatus::Cancelled;
        }

        $today ??= CarbonImmutable::today('UTC');
        $odometer ??= (float) $this->vehicle->current_odometer;
        $kmRemaining = $this->next_due_odometer === null ? null : (float) $this->next_due_odometer - $odometer;
        $daysRemaining = $this->next_due_date === null ? null : $today->diffInDays($this->next_due_date, false);
        if (($kmRemaining !== null && $kmRemaining < 0) || ($daysRemaining !== null && $daysRemaining < 0)) {
            return MaintenancePlanStatus::Overdue;
        }

        if (($kmRemaining !== null && $kmRemaining == 0) || ($daysRemaining !== null && $daysRemaining == 0)) {
            return MaintenancePlanStatus::Due;
        }

        $soonKm = (float) ($this->template->soon_km ?? 1000);
        $soonDays = (int) ($this->template->soon_days ?? 30);

        if (($kmRemaining !== null && $kmRemaining <= $soonKm) || ($daysRemaining !== null && $daysRemaining <= $soonDays)) {
            return MaintenancePlanStatus::Soon;
        }

        return MaintenancePlanStatus::Scheduled;
    }

    public function refreshStatus(): self
    {
        $this->status = $this->calculateStatus();
        $this->save();

        return $this;
    }
}
