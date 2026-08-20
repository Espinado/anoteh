<?php

namespace App\Actions;

use App\Enums\ExpenseCategory;
use App\Enums\ExpenseSource;
use App\Models\Expense;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateExpense
{
    public function execute(
        User $actor,
        ExpenseCategory $category,
        float $netAmount,
        float $taxAmount,
        CarbonInterface $incurredOn,
        ?int $vehicleId = null,
        ?string $vendor = null,
        ?string $referenceNumber = null,
        string $currency = 'EUR',
        ExpenseSource $source = ExpenseSource::Manual,
        ?string $sourceReference = null,
        ?string $notes = null,
    ): Expense {
        if (! $actor->canManage()) {
            throw new AuthorizationException('You are not allowed to create expenses.');
        }

        if ($source === ExpenseSource::Service) {
            throw ValidationException::withMessages([
                'source' => 'Service expenses must be created by CompleteServiceRecord.',
            ]);
        }

        if ($netAmount < 0 || $taxAmount < 0) {
            throw ValidationException::withMessages([
                'amount' => 'Expense amounts must be non-negative.',
            ]);
        }

        $currency = strtoupper($currency);
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw ValidationException::withMessages(['currency' => 'Currency must be an ISO 4217 code.']);
        }

        return DB::transaction(function () use (
            $actor, $category, $netAmount, $taxAmount, $incurredOn, $vehicleId,
            $vendor, $referenceNumber, $currency, $source, $sourceReference, $notes,
        ) {
            $net = round($netAmount, 2, PHP_ROUND_HALF_UP);
            $tax = round($taxAmount, 2, PHP_ROUND_HALF_UP);
            $date = $incurredOn->toImmutable()->utc()->toDateString();

            if ($referenceNumber !== null && Expense::query()
                ->where('source', $source->value)
                ->where('reference_number', $referenceNumber)
                ->where('vendor', $vendor)
                ->whereDate('incurred_on', $date)
                ->where('total_amount', number_format($net + $tax, 2, '.', ''))
                ->exists()) {
                throw ValidationException::withMessages([
                    'reference_number' => 'A matching expense already exists.',
                ]);
            }

            return Expense::query()->create([
                'vehicle_id' => $vehicleId,
                'created_by' => $actor->getKey(),
                'category' => $category,
                'source' => $source,
                'source_reference' => $sourceReference,
                'vendor' => $vendor,
                'reference_number' => $referenceNumber,
                'incurred_on' => $date,
                'currency' => $currency,
                'net_amount' => number_format($net, 2, '.', ''),
                'tax_amount' => number_format($tax, 2, '.', ''),
                'total_amount' => number_format($net + $tax, 2, '.', ''),
                'notes' => $notes,
            ]);
        }, 3);
    }
}
