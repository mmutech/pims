<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Business units now exist only to group warehouses. Departments, cost centres,
 * projects, users, purchase orders and material requisitions no longer carry an
 * explicit business unit because a warehouse (or the document itself) already
 * determines the operating context.
 *
 * Purchase requisitions and request for quotations keep their business unit:
 * neither document has a warehouse, so the business unit remains their only
 * organizational anchor.
 */
return new class extends Migration
{
    /**
     * Tables whose business unit column is redundant once a warehouse exists.
     *
     * @var array<string, array{string, list<string>}>
     */
    private array $redundantColumns = [
        'departments' => ['departments_business_unit_id_is_active_index', ['business_unit_id', 'is_active']],
        'cost_centers' => ['cost_centers_business_unit_id_is_active_index', ['business_unit_id', 'is_active']],
        'projects' => ['projects_business_unit_id_is_active_index', ['business_unit_id', 'is_active']],
        'users' => ['users_business_unit_id_status_index', ['business_unit_id', 'status']],
        'purchase_orders' => ['purchase_orders_bu_status_index', ['business_unit_id', 'status']],
        'material_requisitions' => ['mat_req_bu_status_index', ['business_unit_id', 'status']],
    ];

    public function up(): void
    {
        $this->removeBusinessUnitConditions();

        foreach ($this->redundantColumns as $table => [$index, $columns]) {
            Schema::table($table, function (Blueprint $blueprint) use ($index): void {
                $blueprint->dropForeign(['business_unit_id']);
                $blueprint->dropIndex($index);
                $blueprint->dropColumn('business_unit_id');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->redundantColumns as $table => [$index, $columns]) {
            Schema::table($table, function (Blueprint $blueprint) use ($index, $columns): void {
                $blueprint->foreignId('business_unit_id')->nullable()->constrained()->restrictOnDelete();
                $blueprint->index($columns, $index);
            });
        }
    }

    /**
     * Drop workflow conditions that target the removed business unit attribute.
     */
    private function removeBusinessUnitConditions(): void
    {
        if (! Schema::hasTable('approval_stage_conditions')) {
            return;
        }

        $flowIds = DB::table('approval_flows')
            ->whereIn('document_type', [
                'purchase_order',
                'purchase_order_amendment',
                'material_requisition',
                'stock_adjustment',
                'stock_count',
                'stock_reconciliation',
                'stock_transfer',
                'stock_issue',
                'material_return',
            ])
            ->pluck('id');

        if ($flowIds->isEmpty()) {
            return;
        }

        $stageIds = DB::table('approval_stages')
            ->whereIn('approval_flow_id', $flowIds)
            ->pluck('id');

        if ($stageIds->isEmpty()) {
            return;
        }

        DB::table('approval_stage_conditions')
            ->whereIn('approval_stage_id', $stageIds)
            ->where('field', 'business_unit_id')
            ->delete();
    }
};
