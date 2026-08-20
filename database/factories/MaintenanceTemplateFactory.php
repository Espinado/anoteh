<?php

namespace Database\Factories;

use App\Enums\MaintenanceCategory;
use App\Models\MaintenanceTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MaintenanceTemplate> */
class MaintenanceTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'category' => fake()->randomElement(MaintenanceCategory::cases()),
            'interval_km' => 15000,
            'interval_days' => 365,
            'soon_km' => 1000,
            'soon_days' => 30,
            'recommended_operations' => [
                ['code' => 'inspect', 'label' => 'Visual inspection', 'required' => true],
            ],
            'active' => true,
        ];
    }
}
