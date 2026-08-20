<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\AdminUi;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AcceptanceBlockersTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_is_denied_vehicle_mutations(): void
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

    public function test_vehicle_show_has_no_maintenance_tabs(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($manager)
            ->get(route('vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee($vehicle->registration_number)
            ->assertDontSee(__('app.tabs.service'))
            ->assertDontSee(__('app.tabs.defects'))
            ->assertDontSee(__('app.tabs.documents'));
    }
}
