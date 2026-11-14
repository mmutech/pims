<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove region-based workflow conditions; the attribute no longer exists.
        if (Schema::hasTable('approval_stage_conditions')) {
            DB::table('approval_stage_conditions')->where('field', 'region_id')->delete();
        }

        // Remove orphaned region permissions and their assignments from existing databases.
        if (Schema::hasTable('permissions')) {
            $permissionIds = DB::table('permissions')
                ->where('name', 'like', 'organization.regions.%')
                ->pluck('id');

            if ($permissionIds->isNotEmpty()) {
                if (Schema::hasTable('role_has_permissions')) {
                    DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
                }

                if (Schema::hasTable('model_has_permissions')) {
                    DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
                }

                DB::table('permissions')->whereIn('id', $permissionIds)->delete();
            }
        }

        Schema::table('business_units', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
            $table->dropIndex(['region_id', 'is_active']);
            $table->dropColumn('region_id');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
            $table->dropIndex(['region_id', 'is_active']);
            $table->dropColumn('region_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
            $table->dropIndex(['region_id', 'status']);
            $table->dropColumn('region_id');
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
            $table->dropIndex(['region_id', 'is_active']);
            $table->dropColumn('region_id');
        });

        Schema::dropIfExists('regions');
    }

    public function down(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150)->index();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('business_units', function (Blueprint $table) {
            $table->foreignId('region_id')->nullable()->constrained()->restrictOnDelete();
            $table->index(['region_id', 'is_active']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('region_id')->nullable()->constrained()->restrictOnDelete();
            $table->index(['region_id', 'is_active']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('region_id')->nullable()->constrained()->restrictOnDelete();
            $table->index(['region_id', 'status']);
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->foreignId('region_id')->nullable()->constrained()->restrictOnDelete();
            $table->index(['region_id', 'is_active']);
        });
    }
};
