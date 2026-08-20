<?php

namespace Tests\Feature;

use App\Enums\DefectStatus;
use App\Enums\ExpenseCategory;
use App\Enums\ServiceStatus;
use App\Enums\UserRole;
use App\Livewire\AdminUi;
use App\Models\Defect;
use App\Models\Expense;
use App\Models\MaintenanceTemplate;
use App\Models\OdometerReading;
use App\Models\ServiceRecord;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleMaintenancePlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AcceptanceBlockersTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_is_denied_create_update_and_delete(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($viewer)->get(route('vehicles.create'))->assertForbidden();
        $this->actingAs($viewer)->get(route('vehicles.edit', $vehicle))->assertForbidden();
        Livewire::actingAs($viewer)
            ->test(AdminUi::class, ['section' => 'vehicles'])
            ->call('delete', $vehicle->id)
            ->assertForbidden();
    }

    public function test_manager_can_work_with_domain_but_not_users_or_audit(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $this->actingAs($manager)->get(route('vehicles.create'))->assertOk();
        $this->actingAs($manager)->get(route('users.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('audit.index'))->assertForbidden();
    }

    public function test_duplicate_registration_and_vin_are_validation_errors(): void
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
            ->call('save')
            ->assertHasErrors(['form.registration_number', 'form.vin']);
    }

    public function test_plan_create_uses_action_calculated_due_values(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $vehicle = Vehicle::factory()->create(['current_odometer' => 12000]);
        $template = MaintenanceTemplate::factory()->create(['interval_km' => 5000, 'interval_days' => 180]);

        Livewire::actingAs($manager)
            ->test(AdminUi::class, ['section' => 'plans', 'mode' => 'create'])
            ->set('form.vehicle_id', $vehicle->id)
            ->set('form.maintenance_template_id', $template->id)
            ->set('form.starts_on', '2026-01-10')
            ->call('save')
            ->assertHasNoErrors();

        $plan = VehicleMaintenancePlan::firstOrFail();
        $this->assertSame('12000.0', $plan->last_service_odometer);
        $this->assertSame('17000.0', $plan->next_due_odometer);
        $this->assertSame('2026-07-09', $plan->next_due_date->format('Y-m-d'));
    }

    public function test_defect_valid_and_forbidden_ui_transitions(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $defect = Defect::factory()->create(['status' => DefectStatus::Open]);

        Livewire::actingAs($manager)
            ->test(AdminUi::class, ['section' => 'defects', 'mode' => 'edit', 'recordId' => $defect->id])
            ->set('form.status', DefectStatus::Confirmed->value)
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame(DefectStatus::Confirmed, $defect->refresh()->status);
        $this->assertDatabaseHas('defect_status_histories', [
            'defect_id' => $defect->id,
            'from_status' => DefectStatus::Open->value,
            'to_status' => DefectStatus::Confirmed->value,
        ]);

        Livewire::actingAs($manager)
            ->test(AdminUi::class, ['section' => 'defects', 'mode' => 'edit', 'recordId' => $defect->id])
            ->set('form.status', DefectStatus::Resolved->value)
            ->call('save')
            ->assertHasErrors('form.status');
    }

    public function test_service_completion_and_terminal_status_transition(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $vehicle = Vehicle::factory()->create(['current_odometer' => 5000]);
        $service = ServiceRecord::factory()->create([
            'vehicle_id' => $vehicle->id,
            'created_by' => $manager->id,
            'status' => ServiceStatus::InProgress,
            'odometer' => 5100,
        ]);
        $service->items()->create([
            'type' => 'labor', 'description' => 'Repair', 'unit' => 'hour',
            'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 21,
        ]);

        Livewire::actingAs($manager)
            ->test(AdminUi::class, ['section' => 'service-records', 'mode' => 'show', 'recordId' => $service->id])
            ->call('completeService')
            ->assertHasNoErrors();
        $this->assertSame(ServiceStatus::Completed, $service->refresh()->status);
        $this->assertDatabaseHas('expenses', ['service_record_id' => $service->id, 'total_amount' => 121]);

        Livewire::actingAs($manager)
            ->test(AdminUi::class, ['section' => 'service-records', 'mode' => 'edit', 'recordId' => $service->id])
            ->set('form.status', ServiceStatus::InProgress->value)
            ->call('save')
            ->assertHasErrors('form.status');
    }

    public function test_expenses_by_category_respects_period_vehicle_and_category_filters(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $vehicle = Vehicle::factory()->create();
        $other = Vehicle::factory()->create();
        $this->expense($vehicle, ExpenseCategory::Labor, '2026-02-10', 100, 21, 'EUR');
        $this->expense($vehicle, ExpenseCategory::Labor, '2026-02-11', 50, 10.5, 'EUR');
        $this->expense($vehicle, ExpenseCategory::Parts, '2026-02-12', 30, 6.3, 'EUR');
        $this->expense($other, ExpenseCategory::Labor, '2026-02-10', 999, 0, 'EUR');
        $this->expense($vehicle, ExpenseCategory::Labor, '2026-03-01', 777, 0, 'EUR');

        $response = $this->actingAs($manager)->get(route('reports.print', [
            'type' => 'expenses_by_category', 'vehicle_id' => $vehicle->id,
            'category' => ExpenseCategory::Labor->value, 'from' => '2026-02-01', 'to' => '2026-02-28',
        ]));

        $response->assertOk()->assertSee('150')->assertSee('31.5')->assertSee('181.5')->assertDontSee('999')->assertDontSee('777');
    }

    public function test_cost_per_km_uses_period_boundary_odometer_readings(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $vehicle = Vehicle::factory()->create(['registration_number' => 'PERIOD-1', 'current_odometer' => 1700]);
        $this->reading($vehicle, 1000, '2026-01-09 12:00:00', $manager);
        $this->reading($vehicle, 1100, '2026-01-09 22:00:00', $manager);
        $this->reading($vehicle, 1500, '2026-01-20 21:59:59', $manager);
        $this->reading($vehicle, 1700, '2026-01-20 22:00:00', $manager);
        $this->expense($vehicle, ExpenseCategory::Labor, '2026-01-15', 100, 0, 'EUR');

        $response = $this->actingAs($manager)->get(route('reports.print', [
            'type' => 'cost_per_km', 'vehicle_id' => $vehicle->id,
            'from' => '2026-01-10', 'to' => '2026-01-20',
        ]));

        $response->assertOk()->assertSee('PERIOD-1')->assertSee('500')->assertSee('0.2');
    }

    public function test_cost_per_km_falls_back_to_first_reading_inside_period(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $vehicle = Vehicle::factory()->create(['registration_number' => 'PERIOD-2', 'current_odometer' => 500]);
        $this->reading($vehicle, 200, '2026-01-10 08:00:00', $manager);
        $this->reading($vehicle, 500, '2026-01-20 08:00:00', $manager);
        $this->expense($vehicle, ExpenseCategory::Parts, '2026-01-11', 60, 0, 'EUR');

        $this->actingAs($manager)->get(route('reports.print', [
            'type' => 'cost_per_km', 'vehicle_id' => $vehicle->id,
            'from' => '2026-01-10', 'to' => '2026-01-20',
        ]))->assertOk()->assertSee('300')->assertSee('0.2');
    }

    public function test_all_report_routes_smoke(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        foreach (['expenses', 'expenses_by_category', 'cost_per_km', 'service_history', 'defects', 'maintenance_period', 'downtime', 'expiring_documents'] as $type) {
            $this->actingAs($manager)->get(route('reports.print', ['type' => $type]))->assertOk();
            $this->actingAs($manager)->get(route('reports.download', ['type' => $type, 'format' => 'csv']))->assertOk();
        }
    }

    private function expense(Vehicle $vehicle, ExpenseCategory $category, string $date, float $net, float $tax, string $currency): Expense
    {
        return Expense::create([
            'vehicle_id' => $vehicle->id, 'category' => $category, 'incurred_on' => $date,
            'currency' => $currency, 'net_amount' => $net, 'tax_amount' => $tax,
            'total_amount' => $net + $tax, 'source' => 'manual',
        ]);
    }

    private function reading(Vehicle $vehicle, float $reading, string $at, User $actor): OdometerReading
    {
        return OdometerReading::create([
            'vehicle_id' => $vehicle->id, 'recorded_by' => $actor->id, 'reading' => $reading,
            'recorded_at' => $at, 'source' => 'manual',
        ]);
    }
}
