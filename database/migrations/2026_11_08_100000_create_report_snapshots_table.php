<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8 reporting: immutable daily report snapshots used for trend lines and
 * period comparison.
 *
 * PostgreSQL 16 compatible. Explicit short index names keep the schema inside
 * MySQL's 64-character identifier limit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('report_key', 60);
            $table->date('snapshot_date');
            $table->unsignedInteger('row_count')->default(0);
            $table->decimal('total_quantity', 18, 4)->nullable();
            $table->decimal('total_value', 18, 2)->nullable();
            $table->json('payload');
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['report_key', 'snapshot_date'], 'report_snapshots_key_date_unique');
            $table->index(['snapshot_date', 'report_key'], 'report_snapshots_date_key_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_snapshots');
    }
};
