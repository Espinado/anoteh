<?php

namespace App\Actions;

use App\Enums\DefectStatus;
use App\Enums\ExpenseCategory;
use App\Enums\ExpenseSource;
use App\Enums\ServiceStatus;
use App\Models\Expense;
use App\Models\ServiceRecord;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleMaintenancePlan;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CompleteServiceRecord
{
    public function __construct(private readonly AuditLogger $audit, private readonly ChangeDefectStatus $changeDefectStatus) {}

    public function execute(ServiceRecord $serviceRecord, User $actor, bool $createExpense = true, bool $resolveDefect = true): ServiceRecord
    {
        if (! $actor->canManage()) {
            throw new AuthorizationException('You are not allowed to complete service records.');
        }

        return DB::transaction(function () use ($serviceRecord, $actor, $createExpense, $resolveDefect) {
            $service = ServiceRecord::query()->lockForUpdate()->findOrFail($serviceRecord->getKey());
            if ($service->status === ServiceStatus::Completed) {
                return $service->load(['expense', 'plan', 'defect']);
            }

            if ($service->status === ServiceStatus::Cancelled) {
                throw ValidationException::withMessages(['status' => 'A cancelled service cannot be completed.']);
            }

            $vehicle = Vehicle::query()->lockForUpdate()->findOrFail($service->vehicle_id);

            if ((float) $service->odometer < (float) $vehicle->current_odometer) {
                throw ValidationException::withMessages(['odometer' => 'Service odometer cannot be below the current vehicle odometer.']);
            }

            $items = $service->items()->lockForUpdate()->get();
            if ($items->isNotEmpty()) {
                foreach ($items as $item) {
                    $item->calculateAmounts()->save();
                }

                $service->subtotal = number_format(
                    $items->sum(fn ($item) => (float) $item->net_amount),
                    2,
                    '.',
                    '',
                );
                $service->tax_amount = number_format(
                    $items->sum(fn ($item) => (float) $item->tax_amount),
                    2,
                    '.',
                    '',
                );
                $service->total_amount = number_format(
                    (float) $service->subtotal + (float) $service->tax_amount,
                    2,
                    '.',
                    '',
                );
            }

            $service->status = ServiceStatus::Completed;
            $service->completed_at ??= now('UTC');
            $service->save();

            if ((float) $service->odometer > (float) $vehicle->current_odometer) {
                $vehicle->odometerReadings()->create([
                    'recorded_by' => $actor->getKey(),
                    'reading' => $service->odometer,
                    'recorded_at' => $service->completed_at,
                    'source' => 'service',
                    'notes' => 'Recorded by service record #'.$service->getKey(),
                ]);
                $vehicle->update(['current_odometer' => $service->odometer]);
            }

            if ($service->maintenance_plan_id) {
                $plan = VehicleMaintenancePlan::query()->with(['template', 'vehicle'])->lockForUpdate()->find($service->maintenance_plan_id);

                if ($plan) {
                    $kmInterval = $plan->interval_km ?? $plan->template->interval_km;
                    $dayInterval = $plan->interval_days ?? $plan->template->interval_days;
                    $plan->last_service_odometer = $service->odometer;
                    $plan->last_service_date = $service->completed_at->toDateString();
                    $plan->next_due_odometer = $kmInterval === null ? null : (float) $service->odometer + (float) $kmInterval;
                    $plan->next_due_date = $dayInterval === null ? null : $service->completed_at->toImmutable()->startOfDay()->addDays((int) $dayInterval)->toDateString();
                    $plan->save();
                    $plan->status = $plan->calculateStatus((float) $vehicle->current_odometer);
                    $plan->save();
                }
            }

            if ($createExpense) {
                Expense::query()->updateOrCreate(
                    ['service_record_id' => $service->getKey()],
                    [
                        'vehicle_id' => $service->vehicle_id,
                        'created_by' => $actor->getKey(),
                        'category' => ExpenseCategory::Maintenance,
                        'source' => ExpenseSource::Service,
                        'source_reference' => 'service_record:'.$service->getKey(),
                        'vendor' => $service->provider_name,
                        'reference_number' => $service->reference_number,
                        'incurred_on' => $service->completed_at->toDateString(),
                        'currency' => $service->currency,
                        'net_amount' => $service->subtotal,
                        'tax_amount' => $service->tax_amount,
                        'total_amount' => $service->total_amount,
                        'notes' => 'Generated from completed service record.',
                    ],
                );
            }

            if ($resolveDefect && $service->defect_id) {
                $defect = $service->defect()->first();

                if ($defect && $defect->status !== DefectStatus::Resolved) {
                    $this->changeDefectStatus->execute($defect, DefectStatus::Resolved, $actor, 'Resolved by service record #'.$service->getKey());
                }
            }

            $this->audit->log('service.completed', $service, $actor, [], [
                'status' => ServiceStatus::Completed->value,
                'total_amount' => $service->total_amount,
                'expense_linked' => $createExpense,
            ]);

            return $service->refresh()->load(['expense', 'plan', 'defect']);
        }, 3);
    }
}
