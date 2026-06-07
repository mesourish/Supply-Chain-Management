<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Recreate projects table (previously dropped)
        if (!Schema::hasTable('projects')) {
            Schema::create('projects', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('status')->default('active'); // active, completed, suspended
                $table->timestamps();
            });
        }

        // 2. Extend vehicles table
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->integer('year')->nullable();
            $table->string('fuel_type')->nullable(); // Diesel, Petrol, Electric
            $table->decimal('purchase_cost', 15, 2)->nullable();
            $table->date('purchase_date')->nullable();
            $table->string('lifecycle_stage')->default('active'); // purchase, registration, active, maintenance, depreciation, resale, disposed
            $table->integer('health_score')->default(100);
            $table->string('risk_level')->default('low'); // low, medium, high
            $table->json('digital_twin_status')->nullable();
            $table->decimal('carbon_emissions', 8, 2)->nullable(); // CO2 g/km
            $table->string('qr_code_token')->nullable();
        });

        // 3. Extend drivers table
        Schema::table('drivers', function (Blueprint $table) {
            $table->integer('safety_score')->default(100);
            $table->integer('fuel_efficiency_score')->default(100);
            $table->integer('attendance_score')->default(100);
            $table->string('overall_rating')->default('A');
            $table->integer('rewards_count')->default(0);
            $table->integer('penalties_count')->default(0);
        });

        // 4. Create predictive_maintenances table
        Schema::create('predictive_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->string('likely_issue'); // e.g., Hydraulic Pump, Alternator Failure
            $table->integer('prediction_days_range'); // e.g., within 22 days
            $table->integer('confidence_score'); // e.g., 89%
            $table->string('status')->default('active'); // active, ignored, addressed
            $table->timestamps();
        });

        // 5. Create fuel_anomalies table (for fuel theft detection)
        Schema::create('fuel_anomalies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->decimal('expected_fuel', 8, 2);
            $table->decimal('actual_fuel', 8, 2);
            $table->decimal('variance', 8, 2);
            $table->date('anomaly_date');
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        // 6. Create fleet_expenses table (expense tracking per vehicle/project)
        Schema::create('fleet_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->string('category'); // fuel, insurance, parking, fines, repairs, road_tax, toll
            $table->decimal('amount', 15, 2);
            $table->date('date_incurred');
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->timestamps();
        });

        // 7. Create vehicle_damage_audits table (Damage Audit System)
        Schema::create('vehicle_damage_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->date('audit_date');
            $table->integer('before_trip_scratches')->default(0);
            $table->integer('after_trip_scratches')->default(0);
            $table->integer('before_trip_dents')->default(0);
            $table->integer('after_trip_dents')->default(0);
            $table->text('before_trip_notes')->nullable();
            $table->text('after_trip_notes')->nullable();
            $table->string('status')->default('logged'); // logged, resolved, review_pending
            $table->timestamps();
        });

        // 8. Create equipment_usage_charges table (Construction billing integration)
        Schema::create('equipment_usage_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->decimal('usage_hours', 8, 2);
            $table->decimal('hourly_rate', 8, 2);
            $table->decimal('total_charge', 15, 2);
            $table->date('billing_date');
            $table->string('status')->default('pending'); // pending, posted
            $table->timestamps();
        });

        // 9. Create fleet_marketplace_transfers table (internal vehicle sharing)
        Schema::create('fleet_marketplace_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('from_project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('to_project_id')->constrained('projects')->cascadeOnDelete();
            $table->date('request_date');
            $table->string('status')->default('requested'); // requested, approved, rejected, completed
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fleet_marketplace_transfers');
        Schema::dropIfExists('equipment_usage_charges');
        Schema::dropIfExists('vehicle_damage_audits');
        Schema::dropIfExists('fleet_expenses');
        Schema::dropIfExists('fuel_anomalies');
        Schema::dropIfExists('predictive_maintenances');

        if (Schema::hasTable('drivers')) {
            Schema::table('drivers', function (Blueprint $table) {
                $table->dropColumn([
                    'safety_score', 'fuel_efficiency_score', 'attendance_score', 
                    'overall_rating', 'rewards_count', 'penalties_count'
                ]);
            });
        }

        if (Schema::hasTable('vehicles')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->dropColumn([
                    'brand', 'model', 'year', 'fuel_type', 'purchase_cost', 
                    'purchase_date', 'lifecycle_stage', 'health_score', 
                    'risk_level', 'digital_twin_status', 'carbon_emissions', 
                    'qr_code_token'
                ]);
            });
        }

        Schema::dropIfExists('projects');
    }
};
