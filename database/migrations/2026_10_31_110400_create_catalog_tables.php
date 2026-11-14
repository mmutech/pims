<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('item_categories')->restrictOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name', 150)->index();
            $table->text('description')->nullable();
            $table->boolean('track_batches')->default(false);
            $table->boolean('track_serials')->default(false);
            $table->boolean('requires_quality_inspection')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'is_active']);
        });

        Schema::create('units_of_measure', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->string('symbol', 20);
            $table->string('type', 20)->default('quantity')->index();
            $table->unsignedSmallInteger('decimal_places')->default(4);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('item_categories')->restrictOnDelete();
            $table->string('code', 40)->unique();
            $table->string('name', 200)->index();
            $table->text('description')->nullable();
            $table->string('brand', 100)->nullable()->index();
            $table->string('model', 100)->nullable();
            $table->string('manufacturer_part_number', 100)->nullable();
            $table->string('tracking_type', 20)->default('none')->index();
            $table->foreignId('base_unit_of_measure_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('minimum_stock', 18, 4)->default(0);
            $table->decimal('maximum_stock', 18, 4)->nullable();
            $table->decimal('reorder_level', 18, 4)->default(0);
            $table->decimal('reorder_quantity', 18, 4)->default(0);
            $table->decimal('standard_cost', 18, 4)->default(0);
            $table->string('valuation_method', 30)->default('weighted_average');
            $table->boolean('requires_quality_inspection')->default(true);
            $table->unsignedInteger('shelf_life_days')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category_id', 'is_active']);
            $table->index(['tracking_type', 'is_active']);
        });

        Schema::create('item_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_of_measure_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('conversion_factor', 18, 6)->default(1);
            $table->boolean('is_base')->default(false);
            $table->timestamps();

            $table->unique(['item_id', 'unit_of_measure_id']);
        });

        Schema::create('item_specifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('attribute', 100);
            $table->text('value');
            $table->foreignId('unit_of_measure_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['item_id', 'attribute']);
        });

        Schema::create('item_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->string('batch_number', 80);
            $table->date('manufacture_date')->nullable();
            $table->date('expiry_date')->nullable()->index();
            $table->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();

            $table->unique(['item_id', 'batch_number']);
        });

        Schema::create('item_serial_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('item_batches')->restrictOnDelete();
            $table->string('serial_number', 120)->unique();
            $table->string('status', 20)->default('available')->index();
            $table->foreignId('current_warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->timestamps();

            $table->index(['item_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_serial_numbers');
        Schema::dropIfExists('item_batches');
        Schema::dropIfExists('item_specifications');
        Schema::dropIfExists('item_units');
        Schema::dropIfExists('items');
        Schema::dropIfExists('units_of_measure');
        Schema::dropIfExists('item_categories');
    }
};
