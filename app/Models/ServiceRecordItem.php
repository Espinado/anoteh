<?php

namespace App\Models;

use App\Enums\ServiceItemType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable([
    'service_record_id', 'type', 'description', 'quantity', 'unit',
    'unit_price', 'tax_rate', 'net_amount', 'tax_amount', 'total_amount',
])]
class ServiceRecordItem extends Model
{
    protected function casts(): array
    {
        return [
            'type' => ServiceItemType::class,
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            $item->calculateAmounts();
        });
    }

    public function calculateAmounts(): self
    {
        $quantity = (float) $this->quantity;
        $unitPrice = (float) $this->unit_price;
        $taxRate = (float) $this->tax_rate;

        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be greater than zero.']);
        }

        if ($unitPrice < 0 || $taxRate < 0 || $taxRate > 100) {
            throw ValidationException::withMessages([
                'unit_price' => 'Unit price must be non-negative.',
                'tax_rate' => 'Tax rate must be between 0 and 100.',
            ]);
        }

        $net = round($quantity * $unitPrice, 2, PHP_ROUND_HALF_UP);
        $tax = round($net * $taxRate / 100, 2, PHP_ROUND_HALF_UP);

        $this->net_amount = number_format($net, 2, '.', '');
        $this->tax_amount = number_format($tax, 2, '.', '');
        $this->total_amount = number_format($net + $tax, 2, '.', '');

        return $this;
    }

    public function serviceRecord(): BelongsTo
    {
        return $this->belongsTo(ServiceRecord::class);
    }
}
