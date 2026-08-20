<?php

namespace App\Services\Odometer;

use App\Models\Vehicle;

final class ManualOdometerProvider implements OdometerProviderInterface
{
    public function latestFor(Vehicle $vehicle): ?OdometerReadingSnapshot
    {
        $reading = $vehicle->odometerReadings()->latest('recorded_at')->latest('id')->first();

        if ($reading === null) {
            return null;
        }

        return new OdometerReadingSnapshot(
            reading: (float) $reading->reading,
            recordedAt: $reading->recorded_at,
            source: $reading->source,
        );
    }

    public function supports(Vehicle $vehicle): bool
    {
        return true;
    }
}
