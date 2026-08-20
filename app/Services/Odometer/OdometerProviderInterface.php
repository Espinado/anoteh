<?php

namespace App\Services\Odometer;

use App\Models\Vehicle;

interface OdometerProviderInterface
{
    public function latestFor(Vehicle $vehicle): ?OdometerReadingSnapshot;

    public function supports(Vehicle $vehicle): bool;
}
