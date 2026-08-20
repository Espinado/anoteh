<?php

namespace App\Models;

use App\Enums\DefectSeverity;
use App\Enums\DefectStatus;
use App\Enums\FuelType;
use App\Enums\VehicleCategory;
use App\Enums\VehicleStatus;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'registration_number', 'vin', 'make', 'model', 'year', 'status',
    'category', 'body_type', 'fuel_type', 'commissioned_on',
    'inspection_until', 'octa_until', 'responsible_user_id',
    'primary_attachment_id', 'current_odometer', 'notes',
])]
class Vehicle extends Model
{
    use HasAttachments, HasFactory, SoftDeletes;

    protected $appends = ['health'];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'status' => VehicleStatus::class,
            'category' => VehicleCategory::class,
            'fuel_type' => FuelType::class,
            'commissioned_on' => 'immutable_date',
            'inspection_until' => 'immutable_date',
            'octa_until' => 'immutable_date',
            'current_odometer' => 'decimal:1',
        ];
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function primaryAttachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'primary_attachment_id');
    }

    public function odometerReadings(): HasMany
    {
        return $this->hasMany(OdometerReading::class);
    }

    public function maintenancePlans(): HasMany
    {
        return $this->hasMany(VehicleMaintenancePlan::class);
    }

    public function defects(): HasMany
    {
        return $this->hasMany(Defect::class);
    }

    public function serviceRecords(): HasMany
    {
        return $this->hasMany(ServiceRecord::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(VehicleDocument::class);
    }

    public function isRoadworthy(): bool
    {
        if (in_array($this->status, [VehicleStatus::OutOfService, VehicleStatus::WrittenOff], true)) {
            return false;
        }

        return ! $this->defects()
            ->where('severity', DefectSeverity::Critical->value)
            ->whereNotIn('status', [DefectStatus::Resolved->value, DefectStatus::Rejected->value])
            ->exists();
    }

    protected function health(): Attribute
    {
        return Attribute::get(function (): string {
            if (! $this->isRoadworthy()) {
                return 'critical';
            }

            $hasUnresolvedDefects = $this->defects()
                ->whereNotIn('status', [DefectStatus::Resolved->value, DefectStatus::Rejected->value])
                ->exists();

            return $hasUnresolvedDefects ? 'attention' : 'healthy';
        });
    }
}
