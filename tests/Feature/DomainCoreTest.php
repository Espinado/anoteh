<?php

namespace Tests\Feature;

use App\Actions\CompleteServiceRecord;
use App\Actions\CreateExpense;
use App\Actions\CreateMaintenancePlan;
use App\Actions\RecordOdometerReading;
use App\Enums\DefectSeverity;
use App\Enums\DefectStatus;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\ExpenseCategory;
use App\Enums\MaintenanceCategory;
use App\Enums\ServiceStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Defect;
use App\Models\MaintenanceTemplate;
use App\Models\ServiceRecord;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Models\VehicleMaintenancePlan;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DomainCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_odometer_regression_requires_admin_override_and_reason(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $vehicle = Vehicle::factory()->create(['current_odometer' => 1000]);
        $action = app(RecordOdometerReading::class);

        try {
            $action->execute($vehicle, 900, $manager);
            $this->fail('Regression was accepted without an override.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('odometer_readings', 0);
        }

        $reading = $action->execute($vehicle, 900, $admin, adminOverride: true, overrideReason: 'Correcting an import error');

        $this->assertTrue($reading->is_admin_override);
        $this->assertSame('1000.0', $vehicle->refresh()->current_odometer);
        $this->assertDatabaseHas('audit_logs', ['event' => 'odometer.overridden']);
    }

    public function test_service_completion_is_transactional_and_does_not_duplicate_expense(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $vehicle = Vehicle::factory()->create(['current_odometer' => 50000]);
        $template = MaintenanceTemplate::factory()->create([
            'category' => MaintenanceCategory::Engine,
            'interval_km' => 10000,
            'interval_days' => 365,
        ]);
        $plan = VehicleMaintenancePlan::query()->create([
            'vehicle_id' => $vehicle->id,
            'maintenance_template_id' => $template->id,
            'next_due_odometer' => 50000,
            'next_due_date' => now('UTC')->toDateString(),
            'status' => 'due',
        ]);
        $defect = Defect::factory()->create([
            'vehicle_id' => $vehicle->id,
            'reported_by' => $manager->id,
            'severity' => DefectSeverity::High,
            'status' => DefectStatus::Open,
        ]);
        $service = ServiceRecord::query()->create([
            'vehicle_id' => $vehicle->id,
            'maintenance_plan_id' => $plan->id,
            'defect_id' => $defect->id,
            'created_by' => $manager->id,
            'status' => ServiceStatus::InProgress,
            'completed_at' => now('UTC'),
            'odometer' => 51000,
            'currency' => 'EUR',
            'tax_amount' => 21,
        ]);
        $service->items()->create([
            'type' => 'labor',
            'description' => 'Scheduled service',
            'quantity' => 1,
            'unit' => 'hour',
            'unit_price' => 100,
            'tax_rate' => 21,
        ]);

        $action = app(CompleteServiceRecord::class);
        $action->execute($service, $manager);
        $action->execute($service->refresh(), $manager);

        $this->assertDatabaseCount('expenses', 1);
        $this->assertSame(DefectStatus::Resolved, $defect->refresh()->status);
        $this->assertSame('61000.0', $plan->refresh()->next_due_odometer);
        $this->assertSame('121.00', $service->refresh()->total_amount);
        $this->assertDatabaseHas('audit_logs', ['event' => 'service.completed']);
    }

    public function test_daily_reminders_are_idempotent(): void
    {
        Notification::fake();
        User::factory()->create(['role' => UserRole::Manager]);
        $vehicle = Vehicle::factory()->create(['current_odometer' => 10000]);
        $template = MaintenanceTemplate::factory()->create(['interval_km' => 10000]);
        VehicleMaintenancePlan::query()->create([
            'vehicle_id' => $vehicle->id,
            'maintenance_template_id' => $template->id,
            'next_due_odometer' => 10000,
            'next_due_date' => '2026-08-20',
            'status' => 'due',
        ]);

        $this->artisan('anoteh:send-reminders', ['--date' => '2026-08-20'])->assertSuccessful();
        $this->artisan('anoteh:send-reminders', ['--date' => '2026-08-20'])->assertSuccessful();

        $this->assertDatabaseCount('reminder_deliveries', 2);
        Notification::assertCount(2);
    }

    public function test_plan_creation_uses_vehicle_and_template_baselines(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $vehicle = Vehicle::factory()->create(['current_odometer' => 75000]);
        $template = MaintenanceTemplate::factory()->create([
            'interval_km' => 15000,
            'interval_days' => 180,
        ]);

        $plan = app(CreateMaintenancePlan::class)->execute(
            $vehicle,
            $template,
            $manager,
            startsOn: CarbonImmutable::parse('2026-08-20', 'UTC'),
        );

        $this->assertSame('90000.0', $plan->next_due_odometer);
        $this->assertSame('2027-02-16', $plan->next_due_date->toDateString());
    }

    public function test_critical_unresolved_defect_makes_vehicle_unroadworthy(): void
    {
        $vehicle = Vehicle::factory()->create();
        Defect::factory()->create([
            'vehicle_id' => $vehicle->id,
            'severity' => DefectSeverity::Critical,
            'status' => DefectStatus::Confirmed,
        ]);

        $this->assertFalse($vehicle->isRoadworthy());
        $this->assertSame('critical', $vehicle->health);
    }

    public function test_document_status_uses_record_warning_days(): void
    {
        CarbonImmutable::setTestNow('2026-08-20 12:00:00 UTC');
        $document = VehicleDocument::query()->create([
            'vehicle_id' => Vehicle::factory()->create()->id,
            'type' => DocumentType::Insurance,
            'expires_on' => '2026-09-10',
            'warning_days' => 30,
        ]);

        $this->assertSame(DocumentStatus::ExpiringSoon, $document->status);
        CarbonImmutable::setTestNow();
    }

    public function test_manual_expense_action_rejects_duplicate_reference(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $action = app(CreateExpense::class);
        $date = CarbonImmutable::parse('2026-08-20', 'UTC');

        $action->execute($manager, ExpenseCategory::InspectionFees, 100, 21, $date, vendor: 'Inspector', referenceNumber: 'INV-1');
        $this->assertDatabaseHas('audit_logs', ['event' => 'expense.created']);

        $this->expectException(ValidationException::class);
        $action->execute($manager, ExpenseCategory::InspectionFees, 100, 21, $date, vendor: 'Inspector', referenceNumber: 'INV-1');
    }

    public function test_critical_defect_document_and_warranty_reminders_are_idempotent(): void
    {
        Notification::fake();
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $vehicle = Vehicle::factory()->create(['current_odometer' => 50000]);
        Defect::factory()->create([
            'vehicle_id' => $vehicle->id,
            'reported_by' => $manager->id,
            'severity' => DefectSeverity::Critical,
            'status' => DefectStatus::Confirmed,
        ]);
        VehicleDocument::query()->create([
            'vehicle_id' => $vehicle->id,
            'type' => DocumentType::Inspection,
            'expires_on' => '2026-08-30',
            'warning_days' => 15,
        ]);
        ServiceRecord::factory()->create([
            'vehicle_id' => $vehicle->id,
            'created_by' => $manager->id,
            'status' => ServiceStatus::Completed,
            'completed_at' => '2026-08-01',
            'odometer' => 49000,
            'warranty_until_date' => '2026-09-01',
            'warranty_until_odometer' => 50500,
        ]);

        $this->artisan('anoteh:send-reminders', ['--date' => '2026-08-20'])->assertSuccessful();
        $this->artisan('anoteh:send-reminders', ['--date' => '2026-08-20'])->assertSuccessful();

        $this->assertDatabaseCount('reminder_deliveries', 4);
        Notification::assertCount(4);
    }

    public function test_audit_logs_are_admin_only_and_vehicle_changes_are_audited(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $vehicle = Vehicle::factory()->create();
        $vehicle->update(['body_type' => 'audit_test_body']);
        $audit = AuditLog::query()->where('event', 'vehicle.updated')->latest('id')->firstOrFail();

        $this->assertFalse(Gate::forUser($manager)->allows('view', $audit));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $audit));
        $this->assertNull($audit->ip_address);
        $this->assertNull($audit->user_agent);
    }
}
