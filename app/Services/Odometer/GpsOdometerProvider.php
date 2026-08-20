<?php

namespace App\Services\Odometer;

use App\Models\Vehicle;

/**
 * Integration boundary for a future GPS/telematics provider.
 */
final class GpsOdometerProvider implements OdometerProviderInterface
{
    public function latestFor(Vehicle $vehicle): ?OdometerReadingSnapshot
    {
        return null;
    }

    public function supports(Vehicle $vehicle): bool
    {
        return false;
    }
}
