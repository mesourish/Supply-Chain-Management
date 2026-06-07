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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('job_title', 100)->nullable()->after('phone');
            $table->string('department', 100)->nullable()->after('job_title');
            $table->string('employee_id', 50)->nullable()->after('department');
            $table->string('location', 150)->nullable()->after('employee_id');
            $table->string('timezone', 60)->nullable()->default('UTC')->after('location');
            $table->string('language', 10)->nullable()->default('en')->after('timezone');
            $table->text('bio')->nullable()->after('language');
            $table->string('linkedin_url', 255)->nullable()->after('bio');
            $table->string('profile_photo_path', 255)->nullable()->after('linkedin_url');
            $table->string('signature_path', 255)->nullable()->after('profile_photo_path');
            $table->date('date_of_joining')->nullable()->after('signature_path');
            $table->string('emergency_contact_name', 150)->nullable()->after('date_of_joining');
            $table->string('emergency_contact_phone', 30)->nullable()->after('emergency_contact_name');
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->after('emergency_contact_phone');
            $table->boolean('two_factor_enabled')->default(false)->after('status');
            $table->timestamp('last_login_at')->nullable()->after('two_factor_enabled');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone', 'job_title', 'department', 'employee_id', 'location',
                'timezone', 'language', 'bio', 'linkedin_url',
                'profile_photo_path', 'signature_path', 'date_of_joining',
                'emergency_contact_name', 'emergency_contact_phone',
                'status', 'two_factor_enabled', 'last_login_at', 'last_login_ip',
            ]);
        });
    }
};
