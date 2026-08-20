<?php

namespace App\Models;

use App\Enums\ExpenseCategory;
use App\Enums\ExpenseSource;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['vehicle_id', 'service_record_id', 'created_by', 'category', 'source', 'source_reference', 'vendor', 'reference_number', 'incurred_on', 'currency', 'net_amount', 'tax_amount', 'total_amount', 'notes'])]
class Expense extends Model
{
    use HasAttachments, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['category' => ExpenseCategory::class, 'source' => ExpenseSource::class, 'incurred_on' => 'immutable_date', 'net_amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total_amount' => 'decimal:2'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function serviceRecord(): BelongsTo
    {
        return $this->belongsTo(ServiceRecord::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
