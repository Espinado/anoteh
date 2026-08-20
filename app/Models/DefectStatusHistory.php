<?php

namespace App\Models;

use App\Enums\DefectStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['defect_id', 'changed_by', 'from_status', 'to_status', 'note', 'changed_at'])]
class DefectStatusHistory extends Model
{
    protected function casts(): array
    {
        return ['from_status' => DefectStatus::class, 'to_status' => DefectStatus::class, 'changed_at' => 'immutable_datetime'];
    }

    public function defect(): BelongsTo
    {
        return $this->belongsTo(Defect::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
