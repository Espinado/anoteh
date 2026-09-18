<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\VehicleRegulationStatus;
use App\Livewire\VehicleRegulationsPanel;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleRegulation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VehicleRegulationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_add_and_complete_regulation_with_confirmation(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $vehicle = Vehicle::factory()->create();

        Livewire::actingAs($manager)
            ->test(VehicleRegulationsPanel::class, ['vehicle' => $vehicle, 'canManage' => true])
            ->set('newRegulationType', 'Замена масла')
            ->set('newDueOdometer', 150000)
            ->call('addRegulation')
            ->assertHasNoErrors();

        $regulation = VehicleRegulation::firstOrFail();
        $this->assertSame('Замена масла', $regulation->regulation_type);
        $this->assertSame(VehicleRegulationStatus::Planned, $regulation->status);

        Livewire::actingAs($manager)
            ->test(VehicleRegulationsPanel::class, ['vehicle' => $vehicle, 'canManage' => true])
            ->call('requestComplete', $regulation->id)
            ->assertSet('pendingCompleteId', $regulation->id)
            ->call('confirmComplete')
            ->assertSet('pendingCompleteId', null)
            ->assertSet('showCompletedToast', true);

        $regulation->refresh();
        $this->assertSame(VehicleRegulationStatus::Completed, $regulation->status);
        $this->assertNotNull($regulation->completed_at);
        $this->assertSame($manager->id, $regulation->completed_by);
    }

    public function test_cancel_complete_keeps_regulation_planned(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $vehicle = Vehicle::factory()->create();
        $regulation = VehicleRegulation::query()->create([
            'vehicle_id' => $vehicle->id,
            'regulation_type' => 'belt',
            'due_odometer' => 120000,
            'status' => VehicleRegulationStatus::Planned,
        ]);

        Livewire::actingAs($manager)
            ->test(VehicleRegulationsPanel::class, ['vehicle' => $vehicle, 'canManage' => true])
            ->call('requestComplete', $regulation->id)
            ->call('cancelComplete')
            ->assertSet('pendingCompleteId', null);

        $this->assertSame(VehicleRegulationStatus::Planned, $regulation->fresh()->status);
    }
}
