<?php

namespace App\Models;

use App\Enums\MaintenanceCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'category', 'interval_km', 'interval_days', 'soon_km', 'soon_days', 'recommended_operations', 'description', 'active'])]
class MaintenanceTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['category' => MaintenanceCategory::class, 'interval_km' => 'decimal:1', 'interval_days' => 'integer', 'soon_km' => 'decimal:1', 'soon_days' => 'integer', 'recommended_operations' => 'array', 'active' => 'boolean'];
    }

    public function plans(): HasMany
    {
        return $this->hasMany(VehicleMaintenancePlan::class);
    }
}
