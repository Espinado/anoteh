<?php

namespace Tests\Feature;

use App\Enums\ServiceStatus;
use App\Enums\UserRole;
use App\Livewire\AdminUi;
use App\Models\Attachment;
use App\Models\ServiceRecord;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AdminUiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_render_all_administrative_sections(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Vehicle::factory()->create();

        foreach ([
            'dashboard', 'vehicles.index', 'templates.index', 'plans.index', 'defects.index',
            'service-records.index', 'expenses.index', 'documents.index', 'reports.index',
            'notifications.index', 'users.index', 'audit.index', 'profile',
        ] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }
    }

    public function test_manager_can_create_vehicle_from_livewire_ui(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        Livewire::actingAs($manager)
            ->test(AdminUi::class, ['section' => 'vehicles', 'mode' => 'create'])
            ->set('form.registration_number', 'AN-1001')
            ->set('form.vin', 'WVWZZZ1JZXW000001')
            ->set('form.make', 'Volkswagen')
            ->set('form.model', 'Crafter')
            ->set('form.year', 2025)
            ->set('form.status', 'active')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('vehicles', [
            'registration_number' => 'AN-1001',
            'vin' => 'WVWZZZ1JZXW000001',
        ]);
    }

    public function test_odometer_ui_uses_domain_action_and_actor(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $vehicle = Vehicle::factory()->create(['current_odometer' => 1000]);

        Livewire::actingAs($manager)
            ->test(AdminUi::class, ['section' => 'vehicles', 'mode' => 'show', 'recordId' => $vehicle->id])
            ->set('form.odometer', 1250.5)
            ->set('form.odometer_notes', 'Manual inspection')
            ->call('addOdometer')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('odometer_readings', [
            'vehicle_id' => $vehicle->id,
            'recorded_by' => $manager->id,
            'reading' => 1250.5,
            'source' => 'manual',
        ]);
        $this->assertSame('1250.5', $vehicle->refresh()->current_odometer);
    }

    public function test_manager_can_create_service_record_with_calculated_items(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $vehicle = Vehicle::factory()->create(['current_odometer' => 10000]);

        Livewire::actingAs($manager)
            ->test(AdminUi::class, ['section' => 'service-records', 'mode' => 'create'])
            ->set('form.vehicle_id', $vehicle->id)
            ->set('form.status', ServiceStatus::InProgress->value)
            ->set('form.provider_name', 'Anoteh Service')
            ->set('form.started_at', '2026-08-20T10:00')
            ->set('form.odometer', 10100)
            ->set('form.tax_amount', 21)
            ->set('items.0.type', 'labor')
            ->set('items.0.description', 'Scheduled inspection')
            ->set('items.0.quantity', 2)
            ->set('items.0.unit_price', 50)
            ->call('save')
            ->assertHasNoErrors();

        $service = ServiceRecord::firstOrFail();
        $this->assertSame($manager->id, $service->created_by);
        $this->assertSame('100.00', $service->subtotal);
        $this->assertSame('121.00', $service->total_amount);
        $this->assertDatabaseHas('service_record_items', [
            'service_record_id' => $service->id,
            'type' => 'labor',
            'total_amount' => 121,
        ]);
    }

    public function test_attachment_download_route_is_private_and_authorized(): void
    {
        Storage::fake('local');
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $vehicle = Vehicle::factory()->create();
        Storage::disk('local')->put('attachments/test/manual.pdf', 'private contents');
        $attachment = $vehicle->attachments()->create([
            'uploaded_by' => $viewer->id,
            'disk' => 'local',
            'path' => 'attachments/test/manual.pdf',
            'original_name' => 'manual.pdf',
            'mime_type' => 'application/pdf',
            'size' => 16,
            'sha256' => hash('sha256', 'private contents'),
        ]);

        $this->get(route('attachments.download', $attachment))->assertRedirect('/login');
        $this->actingAs($viewer)->get(route('attachments.download', $attachment))
            ->assertOk()
            ->assertHeader('content-disposition');

        $orphan = Attachment::create([
            'uploaded_by' => $viewer->id,
            'disk' => 'local',
            'path' => 'attachments/test/manual.pdf',
            'original_name' => 'orphan.pdf',
            'mime_type' => 'application/pdf',
            'size' => 16,
        ]);
        $this->actingAs($viewer)->get(route('attachments.download', $orphan))->assertForbidden();
    }

    public function test_livewire_upload_uses_private_disk_and_attachment_contract(): void
    {
        Storage::fake('local');
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $vehicle = Vehicle::factory()->create();

        Livewire::actingAs($manager)
            ->test(AdminUi::class, ['section' => 'vehicles', 'mode' => 'show', 'recordId' => $vehicle->id])
            ->set('upload', UploadedFile::fake()->create('inspection.pdf', 120, 'application/pdf'))
            ->call('uploadFile')
            ->assertHasNoErrors();

        $attachment = $vehicle->attachments()->firstOrFail();
        $this->assertSame('inspection.pdf', $attachment->original_name);
        $this->assertSame($manager->id, $attachment->uploaded_by);
        $this->assertSame('local', $attachment->disk);
        $this->assertNotNull($attachment->sha256);
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_vehicle_tabs_are_smoke_ready_and_overview_does_not_query_missing_relation(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $vehicle = Vehicle::factory()->create();
        $component = Livewire::actingAs($manager)
            ->test(AdminUi::class, ['section' => 'vehicles', 'mode' => 'show', 'recordId' => $vehicle->id]);

        foreach (['overview', 'service', 'defects', 'expenses', 'odometer', 'documents', 'files'] as $tab) {
            $component->set('activeTab', $tab)->assertHasNoErrors();
        }
    }

    public function test_only_admin_can_use_trash_restore_and_audit(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $vehicle = Vehicle::factory()->create();
        $vehicle->delete();

        Livewire::actingAs($admin)
            ->test(AdminUi::class, ['section' => 'vehicles'])
            ->set('trash', true)
            ->assertSee($vehicle->registration_number)
            ->call('restore', $vehicle->id)
            ->assertHasNoErrors();

        $this->assertNotSoftDeleted($vehicle);
        $this->actingAs($admin)->get(route('audit.index'))->assertOk();
        $this->actingAs($manager)->get(route('audit.index'))->assertForbidden();
    }

    public function test_reports_support_print_and_csv_routes(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        Vehicle::factory()->create();

        $this->actingAs($manager)
            ->get(route('reports.print', ['type' => 'expenses']))
            ->assertOk()
            ->assertSee(__('app.report_types.expenses'));

        $this->actingAs($manager)
            ->get(route('reports.download', ['type' => 'expenses', 'format' => 'csv']))
            ->assertOk()
            ->assertDownload();
    }
}
