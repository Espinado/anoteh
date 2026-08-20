<?php

namespace App\Enums;

enum VehicleCategory: string
{
    case Truck = 'truck';
    case Van = 'van';
    case PassengerCar = 'passenger_car';
    case Bus = 'bus';
    case Special = 'special';
    case Other = 'other';
}
