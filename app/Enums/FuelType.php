<?php

namespace App\Enums;

enum FuelType: string
{
    case Diesel = 'diesel';
    case Petrol = 'petrol';
    case Electric = 'electric';
    case Hybrid = 'hybrid';
    case Lpg = 'lpg';
    case Cng = 'cng';
    case Hydrogen = 'hydrogen';
    case Other = 'other';
}
