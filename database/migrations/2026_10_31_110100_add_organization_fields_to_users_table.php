<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_number', 30)->nullable()->unique();
            $table->string('phone', 30)->nullable();
            $table->string('job_title', 120)->nullable();
            $table->foreignId('region_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('business_unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('last_login_at')->nullable();
            $table->softDeletes();

            $table->index(['region_id', 'status']);
            $table->index(['business_unit_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
            $table->dropForeign(['business_unit_id']);
            $table->dropForeign(['department_id']);
            $table->dropForeign(['cost_center_id']);
            $table->dropUnique(['employee_number']);
            $table->dropColumn([
                'employee_number',
                'phone',
                'job_title',
                'region_id',
                'business_unit_id',
                'department_id',
                'cost_center_id',
                'status',
                'last_login_at',
                'deleted_at',
            ]);
        });
    }
};
