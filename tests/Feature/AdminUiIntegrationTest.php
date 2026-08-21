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

    public function test_passwordless_mode_logs_guests_in_as_the_first_administrator(): void
    {
        config(['auth.passwordless_access' => true]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->get(route('vehicles.index'))
            ->assertOk();

        $this->assertAuthenticatedAs($admin);
    }

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

    public function test_vehicle_list_supports_safe_sorting_pagination_and_mobile_cards(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        Vehicle::factory()->create(['registration_number' => 'AA-0001', 'make' => 'Audi']);
        Vehicle::factory()->create(['registration_number' => 'ZZ-9999', 'make' => 'Volvo']);
        Vehicle::factory()->count(14)->create();

        Livewire::actingAs($manager)
            ->test(AdminUi::class, ['section' => 'vehicles', 'mode' => 'index'])
            ->set('perPage', 25)
            ->assertViewHas('records', fn ($records) => $records->perPage() === 25 && $records->total() === 16)
            ->assertSee('vehicle-card-')
            ->call('sortBy', 'registration_number')
            ->assertSet('direction', 'desc')
            ->assertSeeInOrder(['ZZ-9999', 'AA-0001'])
            ->set('perPage', 10)
            ->assertViewHas('records', fn ($records) => $records->perPage() === 10 && $records->lastPage() === 2)
            ->set('sort', 'unsafe_column')
            ->set('direction', 'sideways')
            ->set('perPage', 999)
            ->assertSet('sort', 'registration_number')
            ->assertSet('direction', 'asc')
            ->assertSet('perPage', 15);
    }

    public function test_pwa_shell_and_offline_page_are_exposed(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(route('vehicles.index'))
            ->assertOk()
            ->assertSee('/manifest.webmanifest', escape: false)
            ->assertSee('pwa-install-banner');

        $this->get(route('offline'))
            ->assertOk()
            ->assertSee(__('app.offline_title'));
        $this->get(route('serviceworker'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/javascript; charset=UTF-8')
            ->assertSee('autopark-shell-', escape: false)
            ->assertSee('SKIP_WAITING', escape: false);

        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/vehicles', $manifest['start_url']);
        $this->assertSame('Автопарк', $manifest['name']);
        $this->assertFileExists(resource_path('views/serviceworker.blade.php'));
        $this->assertFileExists(public_path('images/icons/icon-512.png'));
        $this->assertStringNotContainsString(
            'localStorage',
            file_get_contents(resource_path('js/pwa-install.js')),
            'The install offer must not remain dismissed after a browser reload.',
        );
    }
}
