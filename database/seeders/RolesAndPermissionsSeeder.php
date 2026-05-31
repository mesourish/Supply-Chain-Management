<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Define specific granular permissions for all modules
        $permissions = [
            'view dashboard',
            'view products', 'create products', 'edit products', 'delete products',
            'view warehouses', 'create warehouses', 'edit warehouses', 'delete warehouses',
            'view suppliers', 'create suppliers', 'edit suppliers', 'delete suppliers',
            'view customers', 'create customers', 'edit customers', 'delete customers',
            'view purchase_orders', 'create purchase_orders', 'edit purchase_orders', 'delete purchase_orders', 'approve purchase_orders', 'receive purchase_orders',
            'view sales_orders', 'create sales_orders', 'edit sales_orders', 'delete sales_orders', 'fulfill sales_orders',
            'view inventory', 'edit inventory', 'view inventory log', 'create inventory adjustments',
            'manage roles', 'manage users', 'manage general_settings', 'manage localization_settings', 'view constants', 'create constants', 'edit constants', 'delete constants',
            'view vehicles', 'create vehicles', 'edit vehicles', 'delete vehicles',
            'view drivers', 'create drivers', 'edit drivers', 'delete drivers',
            'view shipments', 'create shipments', 'edit shipments', 'delete shipments',
            'view grn', 'create grn',
            'view fulfillment', 'create fulfillment',
            'view payables', 'create payables', 'edit payables',
            'view receivables', 'create receivables', 'edit receivables',
            'view returns', 'create returns', 'edit returns',
            'view expenses', 'create expenses', 'edit expenses', 'delete expenses',
            'view invoices', 'create invoices', 'edit invoices', 'delete invoices',
            'view currencies', 'manage currencies',
            'view crm_leads', 'create crm_leads', 'edit crm_leads', 'delete crm_leads',
            'view quotations', 'create quotations', 'edit quotations', 'delete quotations',
            'view rfqs', 'create rfqs', 'edit rfqs', 'delete rfqs',
            'view reports', 'manage reports',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // 2. Create Roles and assign combinations of permissions
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin']);
        $superAdmin->givePermissionTo(Permission::all());

        $procurementManager = Role::firstOrCreate(['name' => 'Procurement Manager']);
        $procurementManager->syncPermissions([
            'view dashboard',
            'view products',
            'view suppliers', 'create suppliers', 'edit suppliers',
            'view purchase_orders', 'create purchase_orders', 'edit purchase_orders', 'approve purchase_orders'
        ]);

        $warehouseStaff = Role::firstOrCreate(['name' => 'Warehouse Staff']);
        $warehouseStaff->syncPermissions([
            'view dashboard',
            'view products', 'create products', 'edit products',
            'view warehouses',
            'view inventory log', 'create inventory adjustments',
            'view purchase_orders', 'receive purchase_orders',
            'view sales_orders', 'fulfill sales_orders'
        ]);

        $salesRep = Role::firstOrCreate(['name' => 'Sales Representative']);
        $salesRep->syncPermissions([
            'view dashboard',
            'view products',
            'view customers', 'create customers', 'edit customers',
            'view sales_orders', 'create sales_orders', 'edit sales_orders',
            'view returns', 'create returns', 'edit returns'
        ]);

        $financeManager = Role::firstOrCreate(['name' => 'Finance Manager']);
        $financeManager->syncPermissions([
            'view dashboard',
            'view payables', 'create payables', 'edit payables',
            'view receivables', 'create receivables', 'edit receivables',
            'view expenses', 'create expenses', 'edit expenses', 'delete expenses',
            'view invoices', 'create invoices', 'edit invoices', 'delete invoices',
            'view currencies', 'manage currencies',
            'view suppliers', 'view customers', 'view purchase_orders', 'view sales_orders'
        ]);

        // 3. Create a test admin user (if not exists)
        $admin = User::firstOrCreate([
            'email' => 'admin@example.com',
        ], [
            'name' => 'Admin User',
            'password' => Hash::make('password'),
        ]);
        $admin->assignRole('Super Admin');
    }
}
