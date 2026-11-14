<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Procurement documents: requisition, sourcing, quotation, evaluation, order,
 * and amendment. All schema is PostgreSQL 16 compatible; explicit short
 * identifiers keep MySQL's 64-character limit satisfied.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('document_number', 40)->unique();
            $table->foreignId('requesting_department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('business_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('suggested_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->date('required_by')->nullable();
            $table->text('justification');
            $table->string('status', 30)->default('draft')->index();
            $table->decimal('total_estimated_amount', 18, 2)->default(0);
            $table->decimal('approved_amount', 18, 2)->default(0);
            $table->char('currency', 3)->default('NGN');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_unit_id', 'status'], 'purchase_requisitions_bu_status_index');
            $table->index(['requesting_department_id', 'status'], 'purchase_requisitions_dept_status_index');
        });

        Schema::create('purchase_requisition_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_requisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('unit_of_measure_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('requested_quantity', 18, 4);
            $table->decimal('approved_quantity', 18, 4)->default(0);
            $table->decimal('converted_quantity', 18, 4)->default(0);
            $table->decimal('estimated_unit_price', 18, 4)->default(0);
            $table->decimal('estimated_line_total', 18, 2)->default(0);
            $table->date('required_by')->nullable();
            $table->text('specification')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('purchase_requisition_id', 'pr_items_requisition_index');
            $table->index(['item_id', 'purchase_requisition_id'], 'pr_items_item_requisition_index');
        });

        Schema::create('request_for_quotations', function (Blueprint $table) {
            $table->id();
            $table->string('document_number', 40)->unique();
            $table->foreignId('purchase_requisition_id')->nullable()->constrained('purchase_requisitions')->nullOnDelete();
            $table->foreignId('business_unit_id')->constrained()->restrictOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->date('closing_date');
            $table->string('status', 30)->default('draft')->index();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('awarded_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_unit_id', 'status'], 'rfq_bu_status_index');
            $table->index('purchase_requisition_id', 'rfq_requisition_index');
        });

        Schema::create('rfq_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_for_quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_requisition_item_id')->nullable()->constrained('purchase_requisition_items')->nullOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('unit_of_measure_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->text('specification')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('request_for_quotation_id', 'rfq_items_rfq_index');
            $table->index('purchase_requisition_item_id', 'rfq_items_pr_item_index');
        });

        Schema::create('rfq_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_for_quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->timestamp('invited_at');
            $table->timestamp('responded_at')->nullable();
            $table->string('status', 20)->default('invited')->index();
            $table->timestamps();

            $table->unique(['request_for_quotation_id', 'supplier_id'], 'rfq_suppliers_rfq_supplier_unique');
        });

        Schema::create('supplier_quotations', function (Blueprint $table) {
            $table->id();
            $table->string('document_number', 40)->unique();
            $table->foreignId('request_for_quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->char('currency', 3)->default('NGN');
            $table->date('valid_until')->nullable();
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->string('payment_terms', 100)->nullable();
            $table->text('warranty_terms')->nullable();
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->string('status', 30)->default('draft')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['request_for_quotation_id', 'supplier_id'], 'supplier_quotations_rfq_supplier_unique');
            $table->index(['request_for_quotation_id', 'status'], 'supplier_quotations_rfq_status_index');
        });

        Schema::create('supplier_quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rfq_item_id')->constrained('rfq_items')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('unit_of_measure_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('tax_rate', 7, 4)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->timestamps();

            $table->unique(['supplier_quotation_id', 'rfq_item_id'], 'supplier_quotation_items_quote_rfq_unique');
        });

        Schema::create('quotation_evaluations', function (Blueprint $table) {
            $table->id();
            $table->string('document_number', 40)->unique();
            $table->foreignId('request_for_quotation_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('draft')->index();
            $table->foreignId('evaluated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('evaluated_at')->nullable();
            $table->foreignId('recommended_quotation_id')->nullable()->constrained('supplier_quotations')->nullOnDelete();
            $table->text('comments')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('request_for_quotation_id', 'quotation_evaluations_rfq_index');
        });

        Schema::create('quotation_evaluation_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quotation_evaluation_id');
            $table->foreignId('supplier_quotation_id')->constrained('supplier_quotations')->cascadeOnDelete();
            $table->string('criterion', 50);
            $table->decimal('weight', 5, 2)->default(0);
            $table->decimal('score', 5, 2)->default(0);
            $table->decimal('weighted_score', 5, 2)->default(0);
            $table->timestamps();

            $table->foreign('quotation_evaluation_id', 'qe_scores_evaluation_fk')
                ->references('id')
                ->on('quotation_evaluations')
                ->cascadeOnDelete();

            $table->unique(
                ['quotation_evaluation_id', 'supplier_quotation_id', 'criterion'],
                'qe_scores_eval_quote_criterion_unique',
            );
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('document_number', 40)->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('business_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('delivery_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('purchase_requisition_id')->nullable()->constrained('purchase_requisitions')->nullOnDelete();
            $table->foreignId('supplier_quotation_id')->nullable()->constrained('supplier_quotations')->nullOnDelete();
            $table->char('currency', 3)->default('NGN');
            $table->string('payment_terms', 100)->nullable();
            $table->string('incoterms', 20)->nullable();
            $table->date('expected_delivery_date')->nullable();
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->string('status', 30)->default('draft')->index();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_unit_id', 'status'], 'purchase_orders_bu_status_index');
            $table->index(['supplier_id', 'status'], 'purchase_orders_supplier_status_index');
            $table->index('delivery_warehouse_id', 'purchase_orders_warehouse_index');
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('unit_of_measure_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('ordered_quantity', 18, 4);
            $table->decimal('received_quantity', 18, 4)->default(0);
            $table->decimal('rejected_quantity', 18, 4)->default(0);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('tax_rate', 7, 4)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);
            $table->date('required_by')->nullable();
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('purchase_requisition_item_id')->nullable()->constrained('purchase_requisition_items')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('purchase_order_id', 'po_items_order_index');
            $table->index(['item_id', 'purchase_order_id'], 'po_items_item_order_index');
        });

        Schema::create('purchase_order_amendments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amendment_number');
            $table->string('reason', 500);
            $table->string('status', 30)->default('draft')->index();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['purchase_order_id', 'amendment_number'], 'po_amendments_order_number_unique');
            $table->index('purchase_order_id', 'po_amendments_order_index');
        });

        Schema::create('purchase_order_amendment_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_amendment_id');
            $table->unsignedBigInteger('purchase_order_item_id');
            $table->decimal('original_quantity', 18, 4);
            $table->decimal('amended_quantity', 18, 4);
            $table->decimal('original_unit_price', 18, 4);
            $table->decimal('amended_unit_price', 18, 4);
            $table->decimal('original_line_total', 18, 2);
            $table->decimal('amended_line_total', 18, 2);
            $table->timestamps();

            $table->foreign('purchase_order_amendment_id', 'po_amendment_items_amendment_fk')
                ->references('id')
                ->on('purchase_order_amendments')
                ->cascadeOnDelete();

            $table->foreign('purchase_order_item_id', 'po_amendment_items_item_fk')
                ->references('id')
                ->on('purchase_order_items')
                ->restrictOnDelete();

            $table->unique(
                ['purchase_order_amendment_id', 'purchase_order_item_id'],
                'po_amendment_items_amendment_item_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_amendment_items');
        Schema::dropIfExists('purchase_order_amendments');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('quotation_evaluation_scores');
        Schema::dropIfExists('quotation_evaluations');
        Schema::dropIfExists('supplier_quotation_items');
        Schema::dropIfExists('supplier_quotations');
        Schema::dropIfExists('rfq_suppliers');
        Schema::dropIfExists('rfq_items');
        Schema::dropIfExists('request_for_quotations');
        Schema::dropIfExists('purchase_requisition_items');
        Schema::dropIfExists('purchase_requisitions');
    }
};
