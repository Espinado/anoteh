<?php

namespace App\Enums;

enum ServiceType: string
{
    case ScheduledMaintenance = 'scheduled_maintenance';
    case Repair = 'repair';
    case Diagnostics = 'diagnostics';
    case Tires = 'tires';
    case Inspection = 'inspection';
    case Other = 'other';
}
