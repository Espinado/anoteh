<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'remindable_type', 'remindable_id', 'kind', 'delivery_date'])]
class ReminderDelivery extends Model
{
    protected function casts(): array
    {
        return ['delivery_date' => 'immutable_date'];
    }
}
