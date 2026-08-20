<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\AdminUi;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminUiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_vehicle_crud_routes_remain_and_legacy_sections_redirect(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        foreach (['vehicles.index', 'vehicles.create'] as $route) {
            $this->actingAs($user)->get(route($route))->assertOk();
        }

        foreach (['/', '/dashboard', '/templates', '/plans', '/service-records', '/defects', '/expenses', '/documents', '/reports', '/notifications', '/users', '/audit'] as $uri) {
            $this->actingAs($user)->get($uri)->assertRedirect('/vehicles');
        }

        foreach (['templates.index', 'plans.index', 'service-records.index', 'defects.index', 'expenses.index', 'documents.index', 'reports.index', 'notifications.index', 'users.index', 'audit.index'] as $name) {
            $this->assertFalse(app('router')->has($name));
        }
    }

    public function test_sidebar_contains_only_vehicles_and_profile_is_in_header(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($user)->get(route('vehicles.index'));

        $response->assertOk()
            ->assertSee(route('vehicles.index'))
            ->assertSee(route('profile'))
            ->assertDontSee(__('app.maintenance_plans'))
            ->assertDontSee(__('app.notifications'));
    }

    public function test_manager_can_create_and_update_simple_vehicle_fields(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        Livewire::actingAs($manager)
            ->test(AdminUi::class, ['section' => 'vehicles', 'mode' => 'create'])
            ->set('form.registration_number', 'AN-1001')
            ->set('form.vin', 'WVWZZZ1JZXW000001')
            ->set('form.make', 'Volkswagen')
            ->set('form.model', 'Crafter')
            ->set('form.year', 2025)
            ->set('form.fuel_type', 'diesel')
            ->set('form.inspection_until', '2027-01-10')
            ->set('form.octa_until', '2026-12-01')
            ->call('save')
            ->assertHasNoErrors();

        $vehicle = Vehicle::firstOrFail();
        $this->assertSame('2027-01-10', $vehicle->inspection_until->toDateString());
        $this->assertSame('2026-12-01', $vehicle->octa_until->toDateString());

        Livewire::actingAs($manager)
            ->test(AdminUi::class, ['section' => 'vehicles', 'mode' => 'edit', 'recordId' => $vehicle->id])
            ->set('form.fuel_type', 'petrol')
            ->set('form.model', 'Crafter II')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id, 'fuel_type' => 'petrol', 'model' => 'Crafter II']);
    }

    public function test_vehicle_validation_enforces_uniques_vin_fuel_and_sane_year(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $existing = Vehicle::factory()->create([
            'registration_number' => 'DUP-001',
            'vin' => 'WVWZZZ1JZXW123456',
        ]);

        Livewire::actingAs($manager)
            ->test(AdminUi::class, ['section' => 'vehicles', 'mode' => 'create'])
            ->set('form.registration_number', $existing->registration_number)
            ->set('form.vin', $existing->vin)
            ->set('form.make', 'Volvo')
            ->set('form.model', 'FH')
            ->set('form.year', 1800)
            ->set('form.fuel_type', 'electric')
            ->call('save')
            ->assertHasErrors([
                'form.registration_number',
                'form.vin',
                'form.year',
                'form.fuel_type',
            ]);
    }
}
