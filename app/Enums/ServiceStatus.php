<?php

namespace App\Enums;

enum ServiceStatus: string
{
    case Scheduled = 'scheduled';
    case Draft = 'draft';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
