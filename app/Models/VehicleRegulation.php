<?php

namespace App\Models;

use App\Enums\VehicleRegulationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'vehicle_id',
    'regulation_type',
    'due_odometer',
    'status',
    'completed_at',
    'completed_by',
])]
class VehicleRegulation extends Model
{
    protected function casts(): array
    {
        return [
            'due_odometer' => 'decimal:1',
            'status' => VehicleRegulationStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function regulationTypeLabel(): string
    {
        $value = trim((string) $this->regulation_type);
        $translationKey = 'app.next_regulation_types.'.$value;
        $translated = __($translationKey);

        return $translated !== $translationKey ? $translated : $value;
    }
}
