<?php

namespace App\Enums;

enum ServiceItemType: string
{
    case Labor = 'labor';
    case Material = 'material';

    /** @deprecated Compatibility with first-stage records. */
    case Part = 'part';
}
