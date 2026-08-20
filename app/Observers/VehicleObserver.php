<?php

namespace App\Observers;

use App\Models\User;
use App\Models\Vehicle;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;

final class VehicleObserver
{
    private const AUDITED = [
        'registration_number',
        'status',
        'category',
        'body_type',
        'fuel_type',
        'commissioned_on',
        'responsible_user_id',
        'primary_attachment_id',
        'current_odometer',
    ];

    public function created(Vehicle $vehicle): void
    {
        $this->log('vehicle.created', $vehicle, [], $vehicle->only(self::AUDITED));
    }

    public function updated(Vehicle $vehicle): void
    {
        $changes = array_intersect_key($vehicle->getChanges(), array_flip(self::AUDITED));
        if ($changes === []) {
            return;
        }

        $old = [];
        foreach (array_keys($changes) as $attribute) {
            $old[$attribute] = $vehicle->getRawOriginal($attribute);
        }

        $this->log('vehicle.updated', $vehicle, $old, $changes);
    }

    public function deleted(Vehicle $vehicle): void
    {
        $this->log('vehicle.deleted', $vehicle);
    }

    public function restored(Vehicle $vehicle): void
    {
        $this->log('vehicle.restored', $vehicle);
    }

    private function log(string $event, Vehicle $vehicle, array $old = [], array $new = []): void
    {
        $actor = Auth::user();
        app(AuditLogger::class)->log($event, $vehicle, $actor instanceof User ? $actor : null, $old, $new);
    }
}
