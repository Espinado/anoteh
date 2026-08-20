<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['vehicle_id', 'recorded_by', 'reading', 'recorded_at', 'source', 'is_admin_override', 'override_reason', 'notes'])]
class OdometerReading extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['reading' => 'decimal:1', 'recorded_at' => 'immutable_datetime', 'is_admin_override' => 'boolean'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
