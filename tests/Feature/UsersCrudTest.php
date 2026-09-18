<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\AdminUi;
use App\Livewire\UsersUi;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\UserInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class UsersCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_users_and_send_invitation(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee(__('app.users'));

        Livewire::actingAs($admin)
            ->test(UsersUi::class, ['mode' => 'create'])
            ->set('form.first_name', 'New')
            ->set('form.last_name', 'Manager')
            ->set('form.email', 'manager@example.com')
            ->set('form.phone', '+37120000001')
            ->set('form.role', UserRole::Manager->value)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('users.show', User::where('email', 'manager@example.com')->first()));

        $created = User::where('email', 'manager@example.com')->firstOrFail();
        $this->assertSame('New', $created->first_name);
        $this->assertSame('Manager', $created->last_name);
        $this->assertSame(UserRole::Manager, $created->role);
        $this->assertNull($created->email_verified_at);

        Notification::assertSentTo($created, UserInvitation::class);

        Livewire::actingAs($admin)
            ->test(UsersUi::class, ['mode' => 'edit', 'recordId' => $created->id])
            ->set('form.first_name', 'Updated')
            ->set('form.last_name', 'Person')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Updated Person', $created->fresh()->name);

        Notification::fake();

        Livewire::actingAs($admin)
            ->test(UsersUi::class, ['mode' => 'show', 'recordId' => $created->id])
            ->call('resendInvitation', $created->id)
            ->assertHasNoErrors();

        Notification::assertSentTo($created->fresh(), UserInvitation::class);

        Livewire::actingAs($admin)
            ->test(UsersUi::class, ['mode' => 'index'])
            ->call('delete', $created->id)
            ->assertHasNoErrors();

        $this->assertSoftDeleted('users', ['id' => $created->id]);
    }

    public function test_admin_can_resend_invitation_to_others_but_not_self(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $other = User::factory()->create(['email_verified_at' => now()]);

        Livewire::actingAs($admin)
            ->test(UsersUi::class, ['mode' => 'show', 'recordId' => $admin->id])
            ->call('resendInvitation', $admin->id)
            ->assertForbidden();

        Livewire::actingAs($admin)
            ->test(UsersUi::class, ['mode' => 'show', 'recordId' => $other->id])
            ->call('resendInvitation', $other->id)
            ->assertHasNoErrors();

        Notification::assertSentTo($other, UserInvitation::class);
    }

    public function test_manager_cannot_access_users_crud_or_see_users_menu(): void
    {
        $manager = User::factory()->manager()->create();
        $other = User::factory()->create();

        $this->actingAs($manager)
            ->get(route('users.index'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(route('users.create'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(route('users.show', $other))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(route('vehicles.index'))
            ->assertOk()
            ->assertDontSee(route('users.index'));
    }

    public function test_manager_cannot_delete_vehicles(): void
    {
        $manager = User::factory()->manager()->create();
        $vehicle = Vehicle::factory()->create();

        Livewire::actingAs($manager)
            ->test(AdminUi::class, ['section' => 'vehicles', 'mode' => 'index'])
            ->call('delete', $vehicle->id)
            ->assertForbidden();

        $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id, 'deleted_at' => null]);
    }

    public function test_admin_cannot_delete_self_or_last_admin(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(UsersUi::class, ['mode' => 'index'])
            ->call('delete', $admin->id)
            ->assertForbidden();
    }

    public function test_admin_edit_self_redirects_to_profile(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('users.edit', $admin))
            ->assertRedirect(route('profile'));

        Livewire::actingAs($admin)
            ->test(UsersUi::class, ['mode' => 'edit', 'recordId' => $admin->id])
            ->assertRedirect(route('profile'));
    }
}
