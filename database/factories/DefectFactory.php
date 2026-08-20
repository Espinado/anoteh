<?php

namespace Database\Factories;

use App\Enums\DefectSeverity;
use App\Enums\DefectStatus;
use App\Models\Defect;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Defect> */
class DefectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'reported_by' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'category' => 'mechanical',
            'severity' => fake()->randomElement(DefectSeverity::cases()),
            'status' => DefectStatus::Open,
            'detected_odometer' => fake()->numberBetween(10000, 300000),
            'reported_at' => now('UTC'),
        ];
    }
}
