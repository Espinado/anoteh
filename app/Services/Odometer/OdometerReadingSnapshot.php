<?php

namespace App\Services\Odometer;

use Carbon\CarbonImmutable;

final readonly class OdometerReadingSnapshot
{
    public function __construct(
        public float $reading,
        public CarbonImmutable $recordedAt,
        public string $source,
        public ?string $externalId = null,
    ) {}
}
