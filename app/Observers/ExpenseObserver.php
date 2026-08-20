<?php

namespace App\Observers;

use App\Models\Expense;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;

final class ExpenseObserver
{
    private const AUDITED = [
        'vehicle_id',
        'service_record_id',
        'category',
        'source',
        'source_reference',
        'incurred_on',
        'currency',
        'net_amount',
        'tax_amount',
        'total_amount',
    ];

    public function created(Expense $expense): void
    {
        $this->log('expense.created', $expense, [], $expense->only(self::AUDITED));
    }

    public function updated(Expense $expense): void
    {
        $changes = array_intersect_key($expense->getChanges(), array_flip(self::AUDITED));
        if ($changes === []) {
            return;
        }

        $old = [];
        foreach (array_keys($changes) as $attribute) {
            $old[$attribute] = $expense->getRawOriginal($attribute);
        }

        $this->log('expense.updated', $expense, $old, $changes);
    }

    public function deleted(Expense $expense): void
    {
        $this->log('expense.deleted', $expense);
    }

    public function restored(Expense $expense): void
    {
        $this->log('expense.restored', $expense);
    }

    private function log(string $event, Expense $expense, array $old = [], array $new = []): void
    {
        $actor = Auth::user();
        $actor = $actor instanceof User ? $actor : $expense->creator()->first();
        app(AuditLogger::class)->log($event, $expense, $actor instanceof User ? $actor : null, $old, $new);
    }
}
