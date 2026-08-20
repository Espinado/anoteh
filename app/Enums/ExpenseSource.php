<?php

namespace App\Enums;

enum ExpenseSource: string
{
    case Manual = 'manual';
    case Service = 'service';
    case Import = 'import';
}
