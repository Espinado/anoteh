<?php

namespace App\Enums;

enum MaintenanceCategory: string
{
    case Engine = 'engine';
    case Brakes = 'brakes';
    case Tires = 'tires';
    case Fluids = 'fluids';
    case Inspection = 'inspection';
    case Electrical = 'electrical';
    case Other = 'other';
}
