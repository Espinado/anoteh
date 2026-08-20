<?php

namespace App\Enums;

enum MaintenancePlanStatus: string
{
    case Scheduled = 'scheduled';
    case Soon = 'soon';
    case Due = 'due';
    case Overdue = 'overdue';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
