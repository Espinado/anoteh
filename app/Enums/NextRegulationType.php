<?php

namespace App\Enums;

enum NextRegulationType: string
{
    case OilChange = 'oil_change';
    case BrakePads = 'brake_pads';
    case Belt = 'belt';
}
