<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdminPermissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminRolesTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->me = User::factory()->create(['role' => 'admin', 'name' => 'Sokha Admin']);
        $this->me->assignRole(AdminPermissions::SUPER_ADMIN);
        $this->actingAs($this->me);
    }

    public function test_roles_and_staff_tabs_render_with_friendly_permission_names(): void
    {
        User::factory()->create(['role' => 'admin', 'name' => 'New Staff']);   // no role yet

        $this->get(route('admin.roles'))->assertOk()
            ->assertSee('Permission matrix')->assertSee('Dispatcher')->assertSee('Drivers &amp; Vehicles', false)
            ->assertSee('Protected')->assertSee('Create a role')
            ->assertViewHas('roles', fn ($r) => $r->first()->name === AdminPermissions::SUPER_ADMIN);

        $this->get(route('admin.roles', ['tab' => 'staff']))->assertOk()
            ->assertSee('Sokha Admin')->assertSee('Full access')->assertSee('New Staff')->assertSee("Can't open the admin panel", false);

        $this->get(route('admin.roles', ['tab' => 'staff', 'search' => 'new']))->assertOk()
            ->assertViewHas('staff', fn ($s) => $s->pluck('name')->all() === ['New Staff']);
    }

    public function test_create_edit_and_delete_a_role(): void
    {
        $this->post(route('admin.roles.store'), ['name' => 'Accountant', 'permissions' => ['manage-finance', 'manage-reports']])
            ->assertSessionHas('success');
        $role = Role::findByName('Accountant');
        $this->assertEqualsCanonicalizing(['manage-finance', 'manage-reports'], $role->permissions->pluck('name')->all());

        $this->put(route('admin.roles.update', $role), ['name' => 'Finance Team', 'permissions' => ['manage-finance']])->assertSessionHas('success');
        $this->assertSame(['manage-finance'], $role->fresh()->permissions->pluck('name')->all());

        $this->delete(route('admin.roles.destroy', $role))->assertSessionHas('success');
        $this->assertNull(Role::where('name', 'Finance Team')->first());
    }

    public function test_super_admin_keeps_its_name_and_every_permission(): void
    {
        $super = Role::findByName(AdminPermissions::SUPER_ADMIN);

        $this->put(route('admin.roles.update', $super), ['name' => 'Boss', 'permissions' => ['manage-rides']]);
        $super->refresh();
        $this->assertSame(AdminPermissions::SUPER_ADMIN, $super->name);
        $this->assertCount(count(AdminPermissions::all()), $super->permissions);

        $this->delete(route('admin.roles.destroy', $super))->assertSessionHas('error');
    }

    public function test_admins_cannot_lock_themselves_out(): void
    {
        $roleAdmin = Role::create(['name' => 'Role Manager', 'guard_name' => 'web']);
        $roleAdmin->syncPermissions(['manage-roles', 'manage-users']);
        $this->me->syncRoles([$roleAdmin]);

        // Removing manage-roles from my only role, deleting it, or dropping my own roles are refused.
        $this->put(route('admin.roles.update', $roleAdmin), ['name' => 'Role Manager', 'permissions' => ['manage-users']])->assertSessionHas('error');
        $this->delete(route('admin.roles.destroy', $roleAdmin))->assertSessionHas('error');
        $this->post(route('admin.roles.assign'), ['user_id' => $this->me->id, 'role_ids' => [Role::findByName('Support')->id]])->assertSessionHas('error');
        $this->assertTrue($this->me->fresh()->hasPermissionTo('manage-roles'));

        // Other admins can be changed freely; non-admin accounts can't get roles.
        $other = User::factory()->create(['role' => 'admin']);
        $this->post(route('admin.roles.assign'), ['user_id' => $other->id, 'role_ids' => [Role::findByName('Support')->id]])
            ->assertRedirect(route('admin.roles', ['tab' => 'staff']));
        $this->assertTrue($other->fresh()->hasRole('Support'));

        $driver = User::factory()->create(['role' => 'driver']);
        $this->post(route('admin.roles.assign'), ['user_id' => $driver->id, 'role_ids' => [Role::findByName('Support')->id]])->assertSessionHas('error');
        $this->assertFalse($driver->fresh()->hasRole('Support'));
    }
}
