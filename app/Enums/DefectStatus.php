<?php

namespace App\Enums;

enum DefectStatus: string
{
    case Open = 'open';
    case Confirmed = 'confirmed';
    case InProgress = 'in_progress';
    case InRepair = 'in_repair';
    case Deferred = 'deferred';
    case Resolved = 'resolved';
    case Rejected = 'rejected';
}
