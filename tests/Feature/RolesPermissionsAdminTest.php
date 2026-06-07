<?php

namespace Tests\Feature;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RolesPermissionsAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed standard permissions, roles, and realistic objects
        $this->artisan('db:seed');
    }

    /**
     * Test admin page renders for admin but 403s for others.
     */
    public function test_admin_roles_page_authorization()
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->actingAs($admin);

        $response = $this->get('/admin/roles');
        $response->assertStatus(200);

        // Regular sales rep should get 403
        $salesRep = User::factory()->create();
        $salesRep->assignRole('Sales Representative');
        
        $this->actingAs($salesRep);
        $response2 = $this->get('/admin/roles');
        $response2->assertStatus(403);
    }

    /**
     * Test component loads and initializes correctly.
     */
    public function test_roles_component_mounts_and_loads_roles()
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->actingAs($admin);

        $component = Volt::test('admin.roles');
        $component->assertSet('roleId', Role::first()->id);
        $component->assertSet('isEditing', true);
        $this->assertNotEmpty($component->get('roles'));
        $this->assertNotEmpty($component->get('permissions'));
    }

    /**
     * Test search capability including acronym matches (gl, qc, po, so).
     */
    public function test_permissions_can_be_filtered_by_acronym_search()
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->actingAs($admin);

        // Search "gl" should match General Ledger module
        $component = Volt::test('admin.roles')
            ->set('searchPermission', 'gl')
            ->call('loadData');
        
        $permissions = $component->get('permissions');
        $this->assertArrayHasKey('General Ledger', $permissions);
        $this->assertArrayNotHasKey('Products', $permissions);

        // Search "qc" should match Quality Checks module
        $component = Volt::test('admin.roles')
            ->set('searchPermission', 'qc')
            ->call('loadData');
        
        $permissionsQc = $component->get('permissions');
        $this->assertArrayHasKey('Quality Checks', $permissionsQc);
        $this->assertArrayNotHasKey('General Ledger', $permissionsQc);

        // Search "po" should match Purchase Orders
        $component = Volt::test('admin.roles')
            ->set('searchPermission', 'po')
            ->call('loadData');
        
        $permissionsPo = $component->get('permissions');
        $this->assertArrayHasKey('Purchase Orders', $permissionsPo);
        $this->assertArrayNotHasKey('Sales Orders', $permissionsPo);
    }

    /**
     * Test toggling module permissions.
     */
    public function test_toggle_all_permissions_in_a_module()
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->actingAs($admin);

        $component = Volt::test('admin.roles');
        
        // Select custom role
        $role = Role::firstOrCreate(['name' => 'Custom Role']);
        $component->call('selectRole', $role->id);
        
        // Initially no permissions
        $component->assertSet('selectedPermissions', []);

        // Toggle General Ledger permissions on
        $component->call('toggleModulePermissions', 'General Ledger');
        
        $selected = $component->get('selectedPermissions');
        $glPerms = Permission::where('name', 'like', '%general_ledger%')->pluck('name')->toArray();
        foreach ($glPerms as $perm) {
            $this->assertContains($perm, $selected);
        }

        // Toggle General Ledger permissions off
        $component->call('toggleModulePermissions', 'General Ledger');
        $this->assertEmpty($component->get('selectedPermissions'));
    }

    /**
     * Test creating a new role.
     */
    public function test_can_create_new_role_with_permissions()
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->actingAs($admin);

        $glPerms = Permission::where('name', 'like', '%general_ledger%')->pluck('name')->toArray();

        Volt::test('admin.roles')
            ->call('createNewRole')
            ->set('name', 'General Ledger Accountant')
            ->set('selectedPermissions', $glPerms)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('roles', ['name' => 'General Ledger Accountant']);
        
        $role = Role::findByName('General Ledger Accountant');
        foreach ($glPerms as $perm) {
            $this->assertTrue($role->hasPermissionTo($perm));
        }
    }

    /**
     * Test editing an existing role.
     */
    public function test_can_update_existing_role_permissions()
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->actingAs($admin);

        $role = Role::firstOrCreate(['name' => 'QA Inspector']);
        $qcPerms = Permission::where('name', 'like', '%quality_checks%')->pluck('name')->toArray();

        Volt::test('admin.roles')
            ->call('selectRole', $role->id)
            ->set('selectedPermissions', $qcPerms)
            ->call('save')
            ->assertHasNoErrors();

        $role->refresh();
        foreach ($qcPerms as $perm) {
            $this->assertTrue($role->hasPermissionTo($perm));
        }
    }

    /**
     * Test deleting a role and protection of Super Admin.
     */
    public function test_deleting_role_and_super_admin_protection()
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->actingAs($admin);

        // Try to delete Super Admin
        $superAdmin = Role::findByName('Super Admin');
        Volt::test('admin.roles')
            ->call('delete', $superAdmin->id);
        
        $this->assertDatabaseHas('roles', ['name' => 'Super Admin']);

        // Delete custom role
        $role = Role::firstOrCreate(['name' => 'Temp Role']);
        Volt::test('admin.roles')
            ->call('delete', $role->id);

        $this->assertDatabaseMissing('roles', ['name' => 'Temp Role']);
    }
}
