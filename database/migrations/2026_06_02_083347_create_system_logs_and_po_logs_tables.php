<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create system_logs table
        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('action');
            $table->string('module');
            $table->text('description');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        // 2. Create purchase_order_logs table
        Schema::create('purchase_order_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('action');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 3. Define and register new Spatie permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $perms = [
            'approve_l1 purchase_orders',
            'approve_l2 purchase_orders',
            'view system_logs'
        ];

        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        // 4. Assign new permissions to Super Admin role
        $superAdmin = Role::where('name', 'Super Admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($perms);
        }

        // Assign L1 permission to Finance Manager
        $financeManager = Role::where('name', 'Finance Manager')->first();
        if ($financeManager) {
            $financeManager->givePermissionTo('approve_l1 purchase_orders');
        }

        // Assign L2 permission to Procurement Manager
        $procurementManager = Role::where('name', 'Procurement Manager')->first();
        if ($procurementManager) {
            $procurementManager->givePermissionTo('approve_l2 purchase_orders');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_logs');
        Schema::dropIfExists('system_logs');

        // Delete Spatie permissions
        Permission::whereIn('name', [
            'approve_l1 purchase_orders',
            'approve_l2 purchase_orders',
            'view system_logs'
        ])->delete();
    }
};
