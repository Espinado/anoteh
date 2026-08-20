<?php

namespace App\Enums;

enum ExpenseCategory: string
{
    case Labor = 'labor';
    case Parts = 'parts';
    case Materials = 'materials';
    case Tires = 'tires';
    case Diagnostics = 'diagnostics';
    case InspectionFees = 'inspection_fees';
    case Insurance = 'insurance';
    case Other = 'other';

    /** @deprecated Compatibility with first-stage service expenses. */
    case Maintenance = 'maintenance';

    /** @deprecated Compatibility with imported first-stage records. */
    case Fuel = 'fuel';
    /** @deprecated Compatibility with imported first-stage records. */
    case Registration = 'registration';
    /** @deprecated Compatibility with imported first-stage records. */
    case Toll = 'toll';
}
