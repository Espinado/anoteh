<?php

namespace Database\Seeders;

use App\Actions\CompleteServiceRecord;
use App\Actions\CreateExpense;
use App\Actions\CreateMaintenancePlan;
use App\Actions\RecordOdometerReading;
use App\Enums\DefectSeverity;
use App\Enums\DefectStatus;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\ExpenseCategory;
use App\Enums\FuelType;
use App\Enums\MaintenanceCategory;
use App\Enums\ServiceStatus;
use App\Enums\ServiceType;
use App\Enums\UserRole;
use App\Enums\VehicleCategory;
use App\Enums\VehicleStatus;
use App\Models\Defect;
use App\Models\MaintenanceTemplate;
use App\Models\ServiceRecord;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Models\VehicleMaintenancePlan;
use Illuminate\Database\Seeder;

final class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->create(['name' => 'Anoteh Administrator', 'email' => 'admin@anoteh.local', 'password' => 'password', 'role' => UserRole::Admin, 'email_verified_at' => now('UTC')]);
        $manager = User::query()->create(['name' => 'Fleet Manager', 'email' => 'manager@anoteh.local', 'password' => 'password', 'role' => UserRole::Manager, 'email_verified_at' => now('UTC')]);
        $viewer = User::query()->create(['name' => 'Read Only Viewer', 'email' => 'viewer@anoteh.local', 'password' => 'password', 'role' => UserRole::Viewer, 'email_verified_at' => now('UTC')]);
        $volvo = Vehicle::query()->create(['registration_number' => 'AN-1201', 'vin' => 'YV2RT40A1KB123456', 'make' => 'Volvo', 'model' => 'FH 500', 'year' => 2022, 'status' => VehicleStatus::Active, 'category' => VehicleCategory::Truck, 'body_type' => 'tractor', 'fuel_type' => FuelType::Diesel, 'commissioned_on' => '2022-06-15', 'responsible_user_id' => $manager->id]);
        $scania = Vehicle::query()->create(['registration_number' => 'AN-2207', 'vin' => 'YS2R4X20005512345', 'make' => 'Scania', 'model' => 'R450', 'year' => 2020, 'status' => VehicleStatus::Active, 'category' => VehicleCategory::Truck, 'body_type' => 'curtain_sider', 'fuel_type' => FuelType::Diesel, 'commissioned_on' => '2020-03-10', 'responsible_user_id' => $manager->id]);
        $van = Vehicle::query()->create(['registration_number' => 'AN-0314', 'vin' => 'W1V9076351P654321', 'make' => 'Mercedes-Benz', 'model' => 'Sprinter', 'year' => 2023, 'status' => VehicleStatus::InService, 'category' => VehicleCategory::Van, 'body_type' => 'panel_van', 'fuel_type' => FuelType::Diesel, 'commissioned_on' => '2023-09-01', 'responsible_user_id' => $viewer->id]);
        app(RecordOdometerReading::class)->execute($volvo, 125400, $manager, now('UTC')->subDays(2), 'import');
        app(RecordOdometerReading::class)->execute($scania, 287950, $manager, now('UTC')->subDay(), 'import');
        app(RecordOdometerReading::class)->execute($van, 64320, $manager, now('UTC'), 'manual');
        $oil = MaintenanceTemplate::query()->create(['name' => 'Engine oil and filter', 'category' => MaintenanceCategory::Engine, 'interval_km' => 15000, 'interval_days' => 365, 'soon_km' => 1500, 'soon_days' => 30, 'recommended_operations' => [['code' => 'oil', 'label' => 'Replace engine oil', 'required' => true], ['code' => 'filters', 'label' => 'Replace oil and fuel filters', 'required' => true]], 'description' => 'Manufacturer oil specification and all filters.']);
        $inspection = MaintenanceTemplate::query()->create(['name' => 'Annual technical inspection', 'category' => MaintenanceCategory::Inspection, 'interval_days' => 365, 'soon_days' => 30, 'recommended_operations' => [['code' => 'inspection', 'label' => 'Complete statutory inspection', 'required' => true]]]);
        $brakes = MaintenanceTemplate::query()->create(['name' => 'Brake system inspection', 'category' => MaintenanceCategory::Brakes, 'interval_km' => 30000, 'interval_days' => 180, 'soon_km' => 2000, 'soon_days' => 21, 'recommended_operations' => [['code' => 'pads', 'label' => 'Measure brake pad thickness', 'required' => true], ['code' => 'fluid', 'label' => 'Test brake fluid', 'required' => true]]]);
        $tires = MaintenanceTemplate::query()->create(['name' => 'Tire condition and rotation', 'category' => MaintenanceCategory::Tires, 'interval_km' => 20000, 'soon_km' => 1500, 'recommended_operations' => [['code' => 'tread', 'label' => 'Measure tread depth', 'required' => true]]]);
        $oilPlan = VehicleMaintenancePlan::query()->create(['vehicle_id' => $volvo->id, 'maintenance_template_id' => $oil->id, 'interval_km' => 15000, 'interval_days' => 365, 'last_service_odometer' => 110000, 'last_service_date' => now('UTC')->subYear(), 'next_due_odometer' => 125000, 'next_due_date' => now('UTC')->subDays(2), 'status' => 'overdue']);
        VehicleMaintenancePlan::query()->create(['vehicle_id' => $scania->id, 'maintenance_template_id' => $brakes->id, 'last_service_odometer' => 260000, 'last_service_date' => now('UTC')->subMonths(7), 'next_due_odometer' => 290000, 'next_due_date' => now('UTC')->subDays(5), 'status' => 'overdue']);
        VehicleMaintenancePlan::query()->create(['vehicle_id' => $van->id, 'maintenance_template_id' => $inspection->id, 'last_service_date' => now('UTC')->subMonths(11), 'next_due_date' => now('UTC')->addDays(18), 'status' => 'soon']);
        app(CreateMaintenancePlan::class)->execute($volvo, $tires, $manager);
        $resolvedDefect = Defect::query()->create(['vehicle_id' => $volvo->id, 'reported_by' => $manager->id, 'assigned_to' => $manager->id, 'title' => 'Oil pressure warning', 'description' => 'Intermittent warning under load; inspect during oil service.', 'category' => 'engine', 'severity' => DefectSeverity::High, 'status' => DefectStatus::InRepair, 'detected_odometer' => 125210, 'reported_at' => now('UTC')->subDays(4)]);
        $resolvedDefect->statusHistory()->create(['changed_by' => $manager->id, 'from_status' => null, 'to_status' => DefectStatus::Open, 'note' => 'Reported by driver.', 'changed_at' => now('UTC')->subDays(4)]);
        $resolvedDefect->statusHistory()->create(['changed_by' => $manager->id, 'from_status' => DefectStatus::Open, 'to_status' => DefectStatus::InRepair, 'note' => 'Workshop booked.', 'changed_at' => now('UTC')->subDays(3)]);
        $openDefect = Defect::query()->create(['vehicle_id' => $scania->id, 'reported_by' => $manager->id, 'title' => 'Brake pressure loss', 'description' => 'Pressure drops during a static brake test; vehicle must not operate.', 'category' => 'brakes', 'severity' => DefectSeverity::Critical, 'status' => DefectStatus::Confirmed, 'detected_odometer' => 287950, 'reported_at' => now('UTC')->subDay()]);
        $openDefect->statusHistory()->create(['changed_by' => $manager->id, 'from_status' => null, 'to_status' => DefectStatus::Confirmed, 'note' => 'Confirmed by fleet manager.', 'changed_at' => now('UTC')->subDay()]);
        $service = ServiceRecord::query()->create(['vehicle_id' => $volvo->id, 'maintenance_plan_id' => $oilPlan->id, 'defect_id' => $resolvedDefect->id, 'created_by' => $manager->id, 'status' => ServiceStatus::InProgress, 'service_type' => ServiceType::ScheduledMaintenance, 'provider_name' => 'Baltic Truck Service SIA', 'reference_number' => 'BTS-2026-1842', 'planned_at' => now('UTC')->subDays(2), 'started_at' => now('UTC')->subDay(), 'completed_at' => now('UTC'), 'downtime_minutes' => 360, 'warranty_until_date' => now('UTC')->addDays(24), 'warranty_until_odometer' => 130000, 'odometer' => 125400, 'currency' => 'EUR', 'description' => 'Scheduled oil service with oil pressure diagnostics.', 'internal_notes' => 'Monitor oil pressure values at the next inspection.']);
        $service->items()->createMany([['type' => 'material', 'description' => 'Engine oil and filter kit', 'quantity' => 1, 'unit' => 'kit', 'unit_price' => 420, 'tax_rate' => 21], ['type' => 'labor', 'description' => 'Diagnostics and service labour', 'quantity' => 3, 'unit' => 'hour', 'unit_price' => 60, 'tax_rate' => 21]]);
        app(CompleteServiceRecord::class)->execute($service, $manager);
        app(CreateExpense::class)->execute($manager, ExpenseCategory::InspectionFees, 120, 25.20, now('UTC')->subDays(3), $scania->id, 'Road Safety Centre', 'RSC-88014', notes: 'Brake test and inspection fee.');
        VehicleDocument::query()->create(['vehicle_id' => $volvo->id, 'type' => DocumentType::Insurance, 'number' => 'POL-2026-00184', 'issued_on' => now('UTC')->subYear()->toDateString(), 'expires_on' => now('UTC')->addDays(17)->toDateString(), 'warning_days' => 45, 'status' => DocumentStatus::ExpiringSoon]);
        VehicleDocument::query()->create(['vehicle_id' => $scania->id, 'type' => DocumentType::Inspection, 'number' => 'TA-88421', 'issued_on' => now('UTC')->subYear()->toDateString(), 'expires_on' => now('UTC')->subDays(2)->toDateString(), 'warning_days' => 21, 'status' => DocumentStatus::Expired]);
        VehicleDocument::query()->create(['vehicle_id' => $van->id, 'type' => DocumentType::Registration, 'number' => 'REG-AN0314', 'issued_on' => now('UTC')->subYears(2)->toDateString(), 'warning_days' => 60, 'status' => DocumentStatus::Valid]);
    }
}
