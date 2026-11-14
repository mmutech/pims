<?php

namespace App\Domain\Reporting\Support;

use App\Domain\Reporting\Services\AuditReportService;
use App\Domain\Reporting\Services\InventoryReportService;
use App\Domain\Reporting\Services\ProcurementReportService;
use App\Domain\Reporting\Services\ProjectReportService;
use App\Domain\Reporting\Services\WarehouseReportService;
use App\Support\Permissions;

/**
 * Single source of truth for report keys, permissions, service methods and
 * column definitions. Screens, exports and snapshots all read from here.
 */
final class ReportCatalogue
{
    public const INVENTORY_VALUATION = 'inventory.valuation';

    public const INVENTORY_MOVEMENTS = 'inventory.movements';

    public const INVENTORY_SLOW_MOVING = 'inventory.slow_moving';

    public const INVENTORY_EXPIRING = 'inventory.expiring';

    public const INVENTORY_RECONCILIATION = 'inventory.reconciliation';

    public const PROCUREMENT_ORDERS = 'procurement.orders';

    public const PROCUREMENT_SUPPLIER_SPEND = 'procurement.supplier_spend';

    public const WAREHOUSE_UTILIZATION = 'warehouse.utilization';

    public const WAREHOUSE_THROUGHPUT = 'warehouse.throughput';

    public const PROJECT_CONSUMPTION = 'project.consumption';

    public const AUDIT_ACTIVITY = 'audit.activity';

    public const AUDIT_APPROVALS = 'audit.approvals';

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            self::INVENTORY_VALUATION => [
                'key' => self::INVENTORY_VALUATION,
                'title' => 'Inventory valuation',
                'description' => 'On-hand quantity and value by warehouse and item, derived from posted balances.',
                'group' => 'Inventory',
                'view' => Permissions::REPORTS_INVENTORY_VIEW,
                'export' => Permissions::REPORTS_INVENTORY_EXPORT,
                'service' => InventoryReportService::class,
                'method' => 'valuation',
                'summary' => 'valuationSummary',
                'summary_value' => 'Value',
                'summary_quantity' => 'On hand',
                'value_column' => 'total_value',
                'quantity_column' => 'quantity',
                'columns' => [
                    ['key' => 'item_code', 'label' => 'Item code'],
                    ['key' => 'item_name', 'label' => 'Item'],
                    ['key' => 'warehouse_code', 'label' => 'Warehouse code'],
                    ['key' => 'warehouse_name', 'label' => 'Warehouse'],
                    ['key' => 'quantity', 'label' => 'On hand', 'align' => 'end', 'type' => 'quantity'],
                    ['key' => 'reserved_quantity', 'label' => 'Reserved', 'align' => 'end', 'type' => 'quantity'],
                    ['key' => 'unit_cost', 'label' => 'Average cost', 'align' => 'end', 'type' => 'cost'],
                    ['key' => 'total_value', 'label' => 'Value', 'align' => 'end', 'type' => 'money'],
                    ['key' => 'last_movement_at', 'label' => 'Last movement', 'type' => 'datetime'],
                ],
            ],
            self::INVENTORY_MOVEMENTS => [
                'key' => self::INVENTORY_MOVEMENTS,
                'title' => 'Stock movement summary',
                'description' => 'Inbound and outbound quantities and values by movement type for a date range.',
                'group' => 'Inventory',
                'view' => Permissions::REPORTS_INVENTORY_VIEW,
                'export' => Permissions::REPORTS_INVENTORY_EXPORT,
                'service' => InventoryReportService::class,
                'method' => 'movements',
                'summary' => 'movementsSummary',
                'value_column' => 'net_value',
                'quantity_column' => 'net_quantity',
                'columns' => [
                    ['key' => 'movement_label', 'label' => 'Movement type'],
                    ['key' => 'movements', 'label' => 'Movements', 'align' => 'end', 'type' => 'integer'],
                    ['key' => 'inbound_quantity', 'label' => 'Qty in', 'align' => 'end', 'type' => 'quantity'],
                    ['key' => 'outbound_quantity', 'label' => 'Qty out', 'align' => 'end', 'type' => 'quantity'],
                    ['key' => 'net_quantity', 'label' => 'Net qty', 'align' => 'end', 'type' => 'quantity'],
                    ['key' => 'inbound_value', 'label' => 'Value in', 'align' => 'end', 'type' => 'money'],
                    ['key' => 'outbound_value', 'label' => 'Value out', 'align' => 'end', 'type' => 'money'],
                    ['key' => 'net_value', 'label' => 'Net value', 'align' => 'end', 'type' => 'money'],
                ],
            ],
            self::INVENTORY_SLOW_MOVING => [
                'key' => self::INVENTORY_SLOW_MOVING,
                'title' => 'Slow and non-moving stock',
                'description' => 'Positive balances with no outbound movement inside the selected window.',
                'group' => 'Inventory',
                'view' => Permissions::REPORTS_INVENTORY_VIEW,
                'export' => Permissions::REPORTS_INVENTORY_EXPORT,
                'service' => InventoryReportService::class,
                'method' => 'slowMoving',
                'summary' => 'slowMovingSummary',
                'value_column' => 'total_value',
                'quantity_column' => 'quantity',
                'columns' => [
                    ['key' => 'item_code', 'label' => 'Item code'],
                    ['key' => 'item_name', 'label' => 'Item'],
                    ['key' => 'warehouse_name', 'label' => 'Warehouse'],
                    ['key' => 'quantity', 'label' => 'On hand', 'align' => 'end', 'type' => 'quantity'],
                    ['key' => 'total_value', 'label' => 'Value', 'align' => 'end', 'type' => 'money'],
                    ['key' => 'last_movement_at', 'label' => 'Last movement', 'type' => 'datetime'],
                    ['key' => 'days_since_movement', 'label' => 'Days idle', 'align' => 'end', 'type' => 'integer'],
                ],
            ],
            self::INVENTORY_EXPIRING => [
                'key' => self::INVENTORY_EXPIRING,
                'title' => 'Expiring and expired stock',
                'description' => 'Batch-tracked balances that are expired or reach expiry inside the selected window.',
                'group' => 'Inventory',
                'view' => Permissions::REPORTS_INVENTORY_VIEW,
                'export' => Permissions::REPORTS_INVENTORY_EXPORT,
                'service' => InventoryReportService::class,
                'method' => 'expiring',
                'summary' => 'expiringSummary',
                'value_column' => 'total_value',
                'quantity_column' => 'quantity',
                'columns' => [
                    ['key' => 'item_code', 'label' => 'Item code'],
                    ['key' => 'item_name', 'label' => 'Item'],
                    ['key' => 'warehouse_name', 'label' => 'Warehouse'],
                    ['key' => 'batch_number', 'label' => 'Batch'],
                    ['key' => 'expiry_date', 'label' => 'Expiry', 'type' => 'date'],
                    ['key' => 'days_to_expiry', 'label' => 'Days to expiry', 'align' => 'end', 'type' => 'integer'],
                    ['key' => 'expiry_status', 'label' => 'Status'],
                    ['key' => 'quantity', 'label' => 'On hand', 'align' => 'end', 'type' => 'quantity'],
                    ['key' => 'total_value', 'label' => 'Value', 'align' => 'end', 'type' => 'money'],
                ],
            ],
            self::INVENTORY_RECONCILIATION => [
                'key' => self::INVENTORY_RECONCILIATION,
                'title' => 'Ledger reconciliation',
                'description' => 'Compares posted balances with the append-only ledger and highlights every difference.',
                'group' => 'Inventory',
                'view' => Permissions::REPORTS_INVENTORY_VIEW,
                'export' => Permissions::REPORTS_INVENTORY_EXPORT,
                'service' => InventoryReportService::class,
                'method' => 'reconciliation',
                'summary' => 'reconciliationSummary',
                'value_column' => 'difference_value',
                'quantity_column' => 'difference_quantity',
                'columns' => [
                    ['key' => 'item_code', 'label' => 'Item code'],
                    ['key' => 'item_name', 'label' => 'Item'],
                    ['key' => 'warehouse_name', 'label' => 'Warehouse'],
                    ['key' => 'balance_quantity', 'label' => 'Balance qty', 'align' => 'end', 'type' => 'quantity'],
                    ['key' => 'ledger_quantity', 'label' => 'Ledger qty', 'align' => 'end', 'type' => 'quantity'],
                    ['key' => 'difference_quantity', 'label' => 'Qty difference', 'align' => 'end', 'type' => 'quantity'],
                    ['key' => 'balance_value', 'label' => 'Balance value', 'align' => 'end', 'type' => 'money'],
                    ['key' => 'ledger_value', 'label' => 'Ledger value', 'align' => 'end', 'type' => 'money'],
                    ['key' => 'difference_value', 'label' => 'Value difference', 'align' => 'end', 'type' => 'money'],
                ],
            ],
            self::PROCUREMENT_ORDERS => [
                'key' => self::PROCUREMENT_ORDERS,
                'title' => 'Purchase order register',
                'description' => 'Purchase orders with supplier, status, order date and committed value.',
                'group' => 'Procurement',
                'view' => Permissions::REPORTS_PROCUREMENT_VIEW,
                'export' => Permissions::REPORTS_PROCUREMENT_EXPORT,
                'service' => ProcurementReportService::class,
                'method' => 'orders',
                'summary' => 'ordersSummary',
                'value_column' => 'total_amount',
                'columns' => [
                    ['key' => 'document_number', 'label' => 'Document'],
                    ['key' => 'supplier_name', 'label' => 'Supplier'],
                    ['key' => 'status_label', 'label' => 'Status'],
                    ['key' => 'order_date', 'label' => 'Order date', 'type' => 'date'],
                    ['key' => 'warehouse_name', 'label' => 'Delivery warehouse'],
                    ['key' => 'currency', 'label' => 'Currency'],
                    ['key' => 'total_amount', 'label' => 'Total', 'align' => 'end', 'type' => 'money'],
                ],
            ],
            self::PROCUREMENT_SUPPLIER_SPEND => [
                'key' => self::PROCUREMENT_SUPPLIER_SPEND,
                'title' => 'Supplier spend',
                'description' => 'Committed purchase value grouped by supplier for a date range.',
                'group' => 'Procurement',
                'view' => Permissions::REPORTS_PROCUREMENT_VIEW,
                'export' => Permissions::REPORTS_PROCUREMENT_EXPORT,
                'service' => ProcurementReportService::class,
                'method' => 'supplierSpend',
                'summary' => 'supplierSpendSummary',
                'summary_value' => 'Committed value',
                'value_column' => 'total_amount',
                'columns' => [
                    ['key' => 'supplier_name', 'label' => 'Supplier'],
                    ['key' => 'currency', 'label' => 'Currency'],
                    ['key' => 'orders', 'label' => 'Orders', 'align' => 'end', 'type' => 'integer'],
                    ['key' => 'total_amount', 'label' => 'Committed value', 'align' => 'end', 'type' => 'money'],
                    ['key' => 'last_order_date', 'label' => 'Last order', 'type' => 'date'],
                ],
            ],
            self::WAREHOUSE_UTILIZATION => [
                'key' => self::WAREHOUSE_UTILIZATION,
                'title' => 'Warehouse utilisation',
                'description' => 'Stock quantity, reservations and value held by each warehouse.',
                'group' => 'Warehouse',
                'view' => Permissions::REPORTS_WAREHOUSE_VIEW,
                'export' => Permissions::REPORTS_WAREHOUSE_EXPORT,
                'service' => WarehouseReportService::class,
                'method' => 'utilization',
                'summary' => 'utilizationSummary',
                'summary_value' => 'Value',
                'summary_quantity' => 'On hand',
                'value_column' => 'total_value',
                'quantity_column' => 'quantity',
                'columns' => [
                    ['key' => 'warehouse_code', 'label' => 'Code'],
                    ['key' => 'warehouse_name', 'label' => 'Warehouse'],
                    ['key' => 'item_count', 'label' => 'Items', 'align' => 'end', 'type' => 'integer'],
                    ['key' => 'quantity', 'label' => 'On hand', 'align' => 'end', 'type' => 'quantity'],
                    ['key' => 'reserved_quantity', 'label' => 'Reserved', 'align' => 'end', 'type' => 'quantity'],
                    ['key' => 'available_quantity', 'label' => 'Available', 'align' => 'end', 'type' => 'quantity'],
                    ['key' => 'total_value', 'label' => 'Value', 'align' => 'end', 'type' => 'money'],
                ],
            ],
            self::WAREHOUSE_THROUGHPUT => [
                'key' => self::WAREHOUSE_THROUGHPUT,
                'title' => 'Warehouse throughput',
                'description' => 'Inbound and outbound quantities and values posted per warehouse.',
                'group' => 'Warehouse',
                'view' => Permissions::REPORTS_WAREHOUSE_VIEW,
                'export' => Permissions::REPORTS_WAREHOUSE_EXPORT,
                'service' => WarehouseReportService::class,
                'method' => 'throughput',
                'summary' => 'throughputSummary',
                'value_column' => 'net_value',
                'quantity_column' => 'net_quantity',
                'columns' => [
                    ['key' => 'warehouse_code', 'label' => 'Code'],
                    ['key' => 'warehouse_name', 'label' => 'Warehouse'],
                    ['key' => 'inbound_quantity', 'label' => 'Qty in', 'align' => 'end', 'type' => 'quantity'],
                    ['key' => 'outbound_quantity', 'label' => 'Qty out', 'align' => 'end', 'type' => 'quantity'],
                    ['key' => 'net_quantity', 'label' => 'Net qty', 'align' => 'end', 'type' => 'quantity'],
                    ['key' => 'inbound_value', 'label' => 'Value in', 'align' => 'end', 'type' => 'money'],
                    ['key' => 'outbound_value', 'label' => 'Value out', 'align' => 'end', 'type' => 'money'],
                    ['key' => 'net_value', 'label' => 'Net value', 'align' => 'end', 'type' => 'money'],
                ],
            ],
            self::PROJECT_CONSUMPTION => [
                'key' => self::PROJECT_CONSUMPTION,
                'title' => 'Project consumption',
                'description' => 'Posted stock issue value and quantity grouped by project and item.',
                'group' => 'Project',
                'view' => Permissions::REPORTS_PROJECT_VIEW,
                'export' => Permissions::REPORTS_PROJECT_EXPORT,
                'service' => ProjectReportService::class,
                'method' => 'consumption',
                'summary' => 'consumptionSummary',
                'value_column' => 'total_value',
                'quantity_column' => 'quantity',
                'columns' => [
                    ['key' => 'project_code', 'label' => 'Project code'],
                    ['key' => 'project_name', 'label' => 'Project'],
                    ['key' => 'item_code', 'label' => 'Item code'],
                    ['key' => 'item_name', 'label' => 'Item'],
                    ['key' => 'quantity', 'label' => 'Issued qty', 'align' => 'end', 'type' => 'quantity'],
                    ['key' => 'total_value', 'label' => 'Issued value', 'align' => 'end', 'type' => 'money'],
                ],
            ],
            self::AUDIT_ACTIVITY => [
                'key' => self::AUDIT_ACTIVITY,
                'title' => 'Activity audit trail',
                'description' => 'Append-only activity log filtered by module, event, subject and date.',
                'group' => 'Audit',
                'view' => Permissions::REPORTS_AUDIT_VIEW,
                'export' => Permissions::REPORTS_AUDIT_EXPORT,
                'service' => AuditReportService::class,
                'method' => 'activity',
                'summary' => 'activitySummary',
                'columns' => [
                    ['key' => 'created_at', 'label' => 'When', 'type' => 'datetime'],
                    ['key' => 'log_name', 'label' => 'Module'],
                    ['key' => 'event', 'label' => 'Event'],
                    ['key' => 'description', 'label' => 'Description'],
                    ['key' => 'causer_name', 'label' => 'Actor'],
                    ['key' => 'subject_label', 'label' => 'Subject'],
                ],
            ],
            self::AUDIT_APPROVALS => [
                'key' => self::AUDIT_APPROVALS,
                'title' => 'Approval performance',
                'description' => 'Approval request counts and ageing by document type and status.',
                'group' => 'Audit',
                'view' => Permissions::REPORTS_AUDIT_VIEW,
                'export' => Permissions::REPORTS_AUDIT_EXPORT,
                'service' => AuditReportService::class,
                'method' => 'approvals',
                'summary' => 'approvalsSummary',
                'columns' => [
                    ['key' => 'document_type', 'label' => 'Document type'],
                    ['key' => 'status_label', 'label' => 'Status'],
                    ['key' => 'requests', 'label' => 'Requests', 'align' => 'end', 'type' => 'integer'],
                    ['key' => 'oldest_requested_at', 'label' => 'Oldest request', 'type' => 'datetime'],
                    ['key' => 'latest_requested_at', 'label' => 'Latest request', 'type' => 'datetime'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::all() as $definition) {
            $groups[$definition['group']][$definition['key']] = $definition;
        }

        return $groups;
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }
}
