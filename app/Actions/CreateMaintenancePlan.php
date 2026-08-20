<?php

namespace App\Actions;

use App\Enums\MaintenancePlanStatus;
use App\Models\MaintenanceTemplate;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleMaintenancePlan;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateMaintenancePlan
{
    public function execute(
        Vehicle $vehicle,
        MaintenanceTemplate $template,
        User $actor,
        ?float $intervalKm = null,
        ?int $intervalDays = null,
        ?CarbonImmutable $startsOn = null,
    ): VehicleMaintenancePlan {
        if (! $actor->canManage()) {
            throw new AuthorizationException('You are not allowed to create maintenance plans.');
        }

        $intervalKm ??= $template->interval_km === null ? null : (float) $template->interval_km;
        $intervalDays ??= $template->interval_days;

        if ($intervalKm === null && $intervalDays === null) {
            throw ValidationException::withMessages([
                'interval' => 'A maintenance plan requires a distance or date interval.',
            ]);
        }

        if (($intervalKm !== null && $intervalKm <= 0) || ($intervalDays !== null && $intervalDays <= 0)) {
            throw ValidationException::withMessages([
                'interval' => 'Maintenance intervals must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use ($vehicle, $template, $intervalKm, $intervalDays, $startsOn) {
            $lockedVehicle = Vehicle::query()->lockForUpdate()->findOrFail($vehicle->getKey());
            $today = ($startsOn ?? CarbonImmutable::today('UTC'))->startOfDay();

            $plan = VehicleMaintenancePlan::query()->create([
                'vehicle_id' => $lockedVehicle->getKey(),
                'maintenance_template_id' => $template->getKey(),
                'interval_km' => $intervalKm,
                'interval_days' => $intervalDays,
                'last_service_odometer' => $lockedVehicle->current_odometer,
                'last_service_date' => $today->toDateString(),
                'next_due_odometer' => $intervalKm === null
                    ? null
                    : (float) $lockedVehicle->current_odometer + $intervalKm,
                'next_due_date' => $intervalDays === null
                    ? null
                    : $today->addDays($intervalDays)->toDateString(),
                'status' => MaintenancePlanStatus::Scheduled,
                'active' => true,
            ]);

            $plan->load(['vehicle', 'template']);
            $plan->status = $plan->calculateStatus((float) $lockedVehicle->current_odometer, $today);
            $plan->save();

            return $plan;
        }, 3);
    }
}
