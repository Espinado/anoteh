<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Valid = 'valid';
    case ExpiringSoon = 'expiring_soon';
    case Expired = 'expired';
}
