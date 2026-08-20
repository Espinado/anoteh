<?php

namespace App\Enums;

enum DocumentType: string
{
    case Registration = 'registration';
    case Insurance = 'insurance';
    case Inspection = 'inspection';
    case Permit = 'permit';
    case Lease = 'lease';
    case Other = 'other';
}
