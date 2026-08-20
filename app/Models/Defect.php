<?php

namespace App\Models;

use App\Enums\DefectSeverity;
use App\Enums\DefectStatus;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['vehicle_id', 'reported_by', 'assigned_to', 'title', 'description', 'category', 'severity', 'status', 'detected_odometer', 'reported_at', 'resolved_at'])]
class Defect extends Model
{
    use HasAttachments, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['severity' => DefectSeverity::class, 'status' => DefectStatus::class, 'detected_odometer' => 'decimal:1', 'reported_at' => 'immutable_datetime', 'resolved_at' => 'immutable_datetime'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(DefectStatusHistory::class);
    }
}
