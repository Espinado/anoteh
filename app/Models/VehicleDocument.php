<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Concerns\HasAttachments;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['vehicle_id', 'type', 'number', 'issued_on', 'expires_on', 'warning_days', 'status', 'notes'])]
class VehicleDocument extends Model
{
    use HasAttachments, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['type' => DocumentType::class, 'status' => DocumentStatus::class, 'issued_on' => 'immutable_date', 'expires_on' => 'immutable_date', 'warning_days' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $document): void {
            $document->status = $document->calculateStatus();
        });
    }

    public function calculateStatus(?CarbonImmutable $today = null): DocumentStatus
    {
        if ($this->expires_on === null) {
            return DocumentStatus::Valid;
        }

        $today ??= CarbonImmutable::today('UTC');

        if ($this->expires_on->isBefore($today)) {
            return DocumentStatus::Expired;
        }

        return $this->expires_on->lessThanOrEqualTo($today->addDays($this->warning_days ?? 30))
            ? DocumentStatus::ExpiringSoon
            : DocumentStatus::Valid;
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
