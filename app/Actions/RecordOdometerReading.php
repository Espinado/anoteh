<?php

namespace App\Actions;

use App\Models\OdometerReading;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AuditLogger;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RecordOdometerReading
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function execute(Vehicle $vehicle, float $reading, User $actor, ?CarbonInterface $recordedAt = null, string $source = 'manual', bool $adminOverride = false, ?string $overrideReason = null, ?string $notes = null): OdometerReading
    {
        if (! $actor->canManage()) {
            throw new AuthorizationException('You are not allowed to record odometer readings.');
        }

        if ($reading < 0) {
            throw ValidationException::withMessages(['reading' => 'Odometer reading cannot be negative.']);
        } if ($adminOverride && (! $actor->isAdmin() || blank($overrideReason))) {
            throw ValidationException::withMessages(['override_reason' => 'Only an administrator may override regression and must provide a reason.']);
        }

        return DB::transaction(function () use ($vehicle, $reading, $actor, $recordedAt, $source, $adminOverride, $overrideReason, $notes) {
            $locked = Vehicle::query()->lockForUpdate()->findOrFail($vehicle->getKey());
            $current = (float) $locked->current_odometer;
            if ($reading < $current && ! $adminOverride) {
                throw ValidationException::withMessages(['reading' => "Odometer cannot regress below {$current}."]);
            } $entry = $locked->odometerReadings()->create(['recorded_by' => $actor->getKey(), 'reading' => $reading, 'recorded_at' => ($recordedAt ?? now('UTC'))->utc(), 'source' => $source, 'is_admin_override' => $adminOverride, 'override_reason' => $adminOverride ? $overrideReason : null, 'notes' => $notes]);
            if ($reading >= $current) {
                $locked->update(['current_odometer' => $reading]);
            } $this->audit->log($adminOverride ? 'odometer.overridden' : 'odometer.recorded', $entry, $actor, ['current_odometer' => $current], ['reading' => $reading, 'reason' => $overrideReason]);

            return $entry;
        }, 3);
    }
}
