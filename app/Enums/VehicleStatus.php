<?php

namespace App\Enums;

enum VehicleStatus: string
{
    case Active = 'active';
    case InService = 'in_service';
    case OutOfService = 'out_of_service';
    case Sold = 'sold';
    case WrittenOff = 'written_off';
}
