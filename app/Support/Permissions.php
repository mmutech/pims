<?php

namespace App\Support;

final class Permissions
{
    public const SYSTEM_ACCESS_ALL = 'system.access.all';

    public const DASHBOARD_VIEW = 'dashboard.view';

    public const AUDIT_VIEW = 'audit.view';

    public const ADMIN_USERS_VIEW = 'administration.users.view';

    public const ADMIN_USERS_CREATE = 'administration.users.create';

    public const ADMIN_USERS_UPDATE = 'administration.users.update';

    public const ADMIN_USERS_DELETE = 'administration.users.delete';

    public const ADMIN_ROLES_VIEW = 'administration.roles.view';

    public const ADMIN_ROLES_CREATE = 'administration.roles.create';

    public const ADMIN_ROLES_UPDATE = 'administration.roles.update';

    public const ADMIN_ROLES_DELETE = 'administration.roles.delete';

    public const ADMIN_SETTINGS_VIEW = 'administration.settings.view';

    public const ADMIN_SETTINGS_UPDATE = 'administration.settings.update';

    public const ADMIN_SEQUENCES_VIEW = 'administration.document_sequences.view';

    public const ADMIN_SEQUENCES_UPDATE = 'administration.document_sequences.update';

    public const ORG_BUSINESS_UNITS_VIEW = 'organization.business_units.view';

    public const ORG_BUSINESS_UNITS_CREATE = 'organization.business_units.create';

    public const ORG_BUSINESS_UNITS_UPDATE = 'organization.business_units.update';

    public const ORG_BUSINESS_UNITS_DELETE = 'organization.business_units.delete';

    public const ORG_DEPARTMENTS_VIEW = 'organization.departments.view';

    public const ORG_DEPARTMENTS_CREATE = 'organization.departments.create';

    public const ORG_DEPARTMENTS_UPDATE = 'organization.departments.update';

    public const ORG_DEPARTMENTS_DELETE = 'organization.departments.delete';

    public const ORG_COST_CENTERS_VIEW = 'organization.cost_centers.view';

    public const ORG_COST_CENTERS_CREATE = 'organization.cost_centers.create';

    public const ORG_COST_CENTERS_UPDATE = 'organization.cost_centers.update';

    public const ORG_COST_CENTERS_DELETE = 'organization.cost_centers.delete';

    public const ORG_PROJECTS_VIEW = 'organization.projects.view';

    public const ORG_PROJECTS_CREATE = 'organization.projects.create';

    public const ORG_PROJECTS_UPDATE = 'organization.projects.update';

    public const ORG_PROJECTS_DELETE = 'organization.projects.delete';

    public const ORG_PROJECT_MEMBERS_MANAGE = 'organization.project_members.manage';

    public const WAREHOUSES_VIEW = 'warehouse.warehouses.view';

    public const WAREHOUSES_CREATE = 'warehouse.warehouses.create';

    public const WAREHOUSES_UPDATE = 'warehouse.warehouses.update';

    public const WAREHOUSES_DELETE = 'warehouse.warehouses.delete';

    public const WAREHOUSE_LOCATIONS_VIEW = 'warehouse.locations.view';

    public const WAREHOUSE_LOCATIONS_CREATE = 'warehouse.locations.create';

    public const WAREHOUSE_LOCATIONS_UPDATE = 'warehouse.locations.update';

    public const WAREHOUSE_LOCATIONS_DELETE = 'warehouse.locations.delete';

    public const WAREHOUSE_BINS_VIEW = 'warehouse.bins.view';

    public const WAREHOUSE_BINS_CREATE = 'warehouse.bins.create';

    public const WAREHOUSE_BINS_UPDATE = 'warehouse.bins.update';

    public const WAREHOUSE_BINS_DELETE = 'warehouse.bins.delete';

    public const WAREHOUSE_ACCESS_MANAGE = 'warehouse.access.manage';

    public const CATEGORIES_VIEW = 'catalog.categories.view';

    public const CATEGORIES_CREATE = 'catalog.categories.create';

    public const CATEGORIES_UPDATE = 'catalog.categories.update';

    public const CATEGORIES_DELETE = 'catalog.categories.delete';

    public const UNITS_VIEW = 'catalog.units.view';

    public const UNITS_CREATE = 'catalog.units.create';

    public const UNITS_UPDATE = 'catalog.units.update';

    public const UNITS_DELETE = 'catalog.units.delete';

    public const ITEMS_VIEW = 'catalog.items.view';

    public const ITEMS_CREATE = 'catalog.items.create';

    public const ITEMS_UPDATE = 'catalog.items.update';

    public const ITEMS_DELETE = 'catalog.items.delete';

    public const SUPPLIERS_VIEW = 'supplier.suppliers.view';

    public const SUPPLIERS_CREATE = 'supplier.suppliers.create';

    public const SUPPLIERS_UPDATE = 'supplier.suppliers.update';

    public const SUPPLIERS_DELETE = 'supplier.suppliers.delete';

    public const SUPPLIERS_APPROVE = 'supplier.suppliers.approve';

    public const PROCUREMENT_REQUISITIONS_VIEW = 'procurement.requisitions.view';

    public const PROCUREMENT_REQUISITIONS_CREATE = 'procurement.requisitions.create';

    public const PROCUREMENT_REQUISITIONS_UPDATE = 'procurement.requisitions.update';

    public const PROCUREMENT_REQUISITIONS_DELETE = 'procurement.requisitions.delete';

    public const PROCUREMENT_REQUISITIONS_SUBMIT = 'procurement.requisitions.submit';

    public const PROCUREMENT_REQUISITIONS_CANCEL = 'procurement.requisitions.cancel';

    public const PROCUREMENT_RFQS_VIEW = 'procurement.rfqs.view';

    public const PROCUREMENT_RFQS_CREATE = 'procurement.rfqs.create';

    public const PROCUREMENT_RFQS_UPDATE = 'procurement.rfqs.update';

    public const PROCUREMENT_RFQS_DELETE = 'procurement.rfqs.delete';

    public const PROCUREMENT_RFQS_ISSUE = 'procurement.rfqs.issue';

    public const PROCUREMENT_RFQS_CANCEL = 'procurement.rfqs.cancel';

    public const PROCUREMENT_QUOTATIONS_VIEW = 'procurement.quotations.view';

    public const PROCUREMENT_QUOTATIONS_CREATE = 'procurement.quotations.create';

    public const PROCUREMENT_QUOTATIONS_UPDATE = 'procurement.quotations.update';

    public const PROCUREMENT_QUOTATIONS_DELETE = 'procurement.quotations.delete';

    public const PROCUREMENT_QUOTATIONS_SUBMIT = 'procurement.quotations.submit';

    public const PROCUREMENT_QUOTATIONS_EVALUATE = 'procurement.quotations.evaluate';

    public const PROCUREMENT_ORDERS_VIEW = 'procurement.orders.view';

    public const PROCUREMENT_ORDERS_CREATE = 'procurement.orders.create';

    public const PROCUREMENT_ORDERS_UPDATE = 'procurement.orders.update';

    public const PROCUREMENT_ORDERS_DELETE = 'procurement.orders.delete';

    public const PROCUREMENT_ORDERS_APPROVE = 'procurement.orders.approve';

    public const PROCUREMENT_ORDERS_CANCEL = 'procurement.orders.cancel';

    public const PROCUREMENT_ORDERS_AMEND = 'procurement.orders.amend';

    public const PROCUREMENT_ORDERS_CLOSE = 'procurement.orders.close';

    public const RECEIVING_SRA_VIEW = 'receiving.sra.view';

    public const RECEIVING_SRA_CREATE = 'receiving.sra.create';

    public const RECEIVING_SRA_UPDATE = 'receiving.sra.update';

    public const RECEIVING_SRA_DELETE = 'receiving.sra.delete';

    public const RECEIVING_SRA_SUBMIT = 'receiving.sra.submit';

    public const RECEIVING_SRA_CANCEL = 'receiving.sra.cancel';

    public const RECEIVING_SRA_VERIFY = 'receiving.sra.verify';

    public const RECEIVING_INSPECTIONS_VIEW = 'receiving.inspections.view';

    public const RECEIVING_INSPECTIONS_START = 'receiving.inspections.start';

    public const RECEIVING_INSPECTIONS_RECORD = 'receiving.inspections.record';

    public const RECEIVING_GRN_VIEW = 'receiving.grn.view';

    public const RECEIVING_GRN_CREATE = 'receiving.grn.create';

    public const RECEIVING_GRN_POST = 'receiving.grn.post';

    public const RECEIVING_GRN_CANCEL = 'receiving.grn.cancel';

    public const RECEIVING_GRN_REVERSE = 'receiving.grn.reverse';

    public const RECEIVING_RETURNS_VIEW = 'receiving.returns.view';

    public const RECEIVING_RETURNS_CREATE = 'receiving.returns.create';

    public const RECEIVING_RETURNS_UPDATE = 'receiving.returns.update';

    public const RECEIVING_RETURNS_DELETE = 'receiving.returns.delete';

    public const RECEIVING_RETURNS_POST = 'receiving.returns.post';

    public const RECEIVING_RETURNS_CANCEL = 'receiving.returns.cancel';

    public const INVENTORY_BALANCES_VIEW = 'inventory.balances.view';

    public const INVENTORY_LEDGER_VIEW = 'inventory.ledger.view';

    public const INVENTORY_TRANSACTIONS_VIEW = 'inventory.transactions.view';

    public const INVENTORY_ADJUSTMENTS_VIEW = 'inventory.adjustments.view';

    public const INVENTORY_ADJUSTMENTS_CREATE = 'inventory.adjustments.create';

    public const INVENTORY_ADJUSTMENTS_UPDATE = 'inventory.adjustments.update';

    public const INVENTORY_ADJUSTMENTS_DELETE = 'inventory.adjustments.delete';

    public const INVENTORY_ADJUSTMENTS_SUBMIT = 'inventory.adjustments.submit';

    public const INVENTORY_ADJUSTMENTS_POST = 'inventory.adjustments.post';

    public const INVENTORY_ADJUSTMENTS_REVERSE = 'inventory.adjustments.reverse';

    public const INVENTORY_ADJUSTMENTS_CANCEL = 'inventory.adjustments.cancel';

    public const STOCKTAKING_COUNTS_VIEW = 'stocktaking.counts.view';

    public const STOCKTAKING_COUNTS_CREATE = 'stocktaking.counts.create';

    public const STOCKTAKING_COUNTS_UPDATE = 'stocktaking.counts.update';

    public const STOCKTAKING_COUNTS_DELETE = 'stocktaking.counts.delete';

    public const STOCKTAKING_COUNTS_START = 'stocktaking.counts.start';

    public const STOCKTAKING_COUNTS_COUNT = 'stocktaking.counts.count';

    public const STOCKTAKING_COUNTS_RECOUNT = 'stocktaking.counts.recount';

    public const STOCKTAKING_COUNTS_ANALYSE = 'stocktaking.counts.analyse';

    public const STOCKTAKING_COUNTS_ADJUST = 'stocktaking.counts.adjust';

    public const STOCKTAKING_COUNTS_COMPLETE = 'stocktaking.counts.complete';

    public const STOCKTAKING_COUNTS_CANCEL = 'stocktaking.counts.cancel';

    public const STOCKTAKING_RECONCILIATIONS_VIEW = 'stocktaking.reconciliations.view';

    public const STOCKTAKING_RECONCILIATIONS_PREPARE = 'stocktaking.reconciliations.prepare';

    public const STOCKTAKING_RECONCILIATIONS_APPROVE = 'stocktaking.reconciliations.approve';

    public const STOCKTAKING_RECONCILIATIONS_POST = 'stocktaking.reconciliations.post';

    public const STOCKTAKING_RECONCILE = 'stocktaking.reconcile';

    public const REQUISITION_REQUESTS_VIEW = 'requisition.requests.view';

    public const REQUISITION_REQUESTS_CREATE = 'requisition.requests.create';

    public const REQUISITION_REQUESTS_UPDATE = 'requisition.requests.update';

    public const REQUISITION_REQUESTS_DELETE = 'requisition.requests.delete';

    public const REQUISITION_REQUESTS_SUBMIT = 'requisition.requests.submit';

    public const REQUISITION_REQUESTS_CANCEL = 'requisition.requests.cancel';

    public const REQUISITION_REQUESTS_RESERVE = 'requisition.requests.reserve';

    public const REQUISITION_ISSUES_VIEW = 'requisition.issues.view';

    public const REQUISITION_ISSUES_CREATE = 'requisition.issues.create';

    public const REQUISITION_ISSUES_UPDATE = 'requisition.issues.update';

    public const REQUISITION_ISSUES_DELETE = 'requisition.issues.delete';

    public const REQUISITION_ISSUES_POST = 'requisition.issues.post';

    public const REQUISITION_ISSUES_REVERSE = 'requisition.issues.reverse';

    public const REQUISITION_ISSUES_CANCEL = 'requisition.issues.cancel';

    public const REQUISITION_ISSUES_ACKNOWLEDGE = 'requisition.issues.acknowledge';

    public const DISTRIBUTION_TRANSFERS_VIEW = 'distribution.transfers.view';

    public const DISTRIBUTION_TRANSFERS_CREATE = 'distribution.transfers.create';

    public const DISTRIBUTION_TRANSFERS_UPDATE = 'distribution.transfers.update';

    public const DISTRIBUTION_TRANSFERS_DELETE = 'distribution.transfers.delete';

    public const DISTRIBUTION_TRANSFERS_SUBMIT = 'distribution.transfers.submit';

    public const DISTRIBUTION_TRANSFERS_CANCEL = 'distribution.transfers.cancel';

    public const DISTRIBUTION_TRANSFERS_DISPATCH = 'distribution.transfers.dispatch';

    public const DISTRIBUTION_TRANSFERS_RECEIVE = 'distribution.transfers.receive';

    public const DISTRIBUTION_TRANSFERS_RESOLVE = 'distribution.transfers.resolve';

    public const DISTRIBUTION_RETURNS_VIEW = 'distribution.returns.view';

    public const DISTRIBUTION_RETURNS_CREATE = 'distribution.returns.create';

    public const DISTRIBUTION_RETURNS_UPDATE = 'distribution.returns.update';

    public const DISTRIBUTION_RETURNS_DELETE = 'distribution.returns.delete';

    public const DISTRIBUTION_RETURNS_ASSESS = 'distribution.returns.assess';

    public const DISTRIBUTION_RETURNS_POST = 'distribution.returns.post';

    public const DISTRIBUTION_RETURNS_CANCEL = 'distribution.returns.cancel';

    public const DISTRIBUTION_PROJECT_CONSUMPTION_VIEW = 'distribution.project_consumption.view';

    public const REPORTS_INVENTORY_VIEW = 'reports.inventory.view';

    public const REPORTS_INVENTORY_EXPORT = 'reports.inventory.export';

    public const REPORTS_PROCUREMENT_VIEW = 'reports.procurement.view';

    public const REPORTS_PROCUREMENT_EXPORT = 'reports.procurement.export';

    public const REPORTS_WAREHOUSE_VIEW = 'reports.warehouse.view';

    public const REPORTS_WAREHOUSE_EXPORT = 'reports.warehouse.export';

    public const REPORTS_PROJECT_VIEW = 'reports.project.view';

    public const REPORTS_PROJECT_EXPORT = 'reports.project.export';

    public const REPORTS_AUDIT_VIEW = 'reports.audit.view';

    public const REPORTS_AUDIT_EXPORT = 'reports.audit.export';

    public const WORKFLOW_FLOWS_VIEW = 'workflow.flows.view';

    public const WORKFLOW_FLOWS_CREATE = 'workflow.flows.create';

    public const WORKFLOW_FLOWS_UPDATE = 'workflow.flows.update';

    public const WORKFLOW_FLOWS_DELETE = 'workflow.flows.delete';

    public const WORKFLOW_REQUESTS_VIEW = 'workflow.requests.view';

    public const WORKFLOW_ACT = 'workflow.act';

    public const WORKFLOW_DELEGATE = 'workflow.delegate';

    public const WORKFLOW_ESCALATE = 'workflow.escalate';

    public const WORKFLOW_OVERRIDE = 'workflow.override';

    /**
     * @return array<string, list<string>>
     */
    public static function groups(): array
    {
        return [
            'System' => [
                self::SYSTEM_ACCESS_ALL,
                self::DASHBOARD_VIEW,
                self::AUDIT_VIEW,
            ],
            'Administration' => [
                self::ADMIN_USERS_VIEW,
                self::ADMIN_USERS_CREATE,
                self::ADMIN_USERS_UPDATE,
                self::ADMIN_USERS_DELETE,
                self::ADMIN_ROLES_VIEW,
                self::ADMIN_ROLES_CREATE,
                self::ADMIN_ROLES_UPDATE,
                self::ADMIN_ROLES_DELETE,
                self::ADMIN_SETTINGS_VIEW,
                self::ADMIN_SETTINGS_UPDATE,
                self::ADMIN_SEQUENCES_VIEW,
                self::ADMIN_SEQUENCES_UPDATE,
            ],
            'Organization' => [
                self::ORG_BUSINESS_UNITS_VIEW,
                self::ORG_BUSINESS_UNITS_CREATE,
                self::ORG_BUSINESS_UNITS_UPDATE,
                self::ORG_BUSINESS_UNITS_DELETE,
                self::ORG_DEPARTMENTS_VIEW,
                self::ORG_DEPARTMENTS_CREATE,
                self::ORG_DEPARTMENTS_UPDATE,
                self::ORG_DEPARTMENTS_DELETE,
                self::ORG_COST_CENTERS_VIEW,
                self::ORG_COST_CENTERS_CREATE,
                self::ORG_COST_CENTERS_UPDATE,
                self::ORG_COST_CENTERS_DELETE,
                self::ORG_PROJECTS_VIEW,
                self::ORG_PROJECTS_CREATE,
                self::ORG_PROJECTS_UPDATE,
                self::ORG_PROJECTS_DELETE,
                self::ORG_PROJECT_MEMBERS_MANAGE,
            ],
            'Warehouse' => [
                self::WAREHOUSES_VIEW,
                self::WAREHOUSES_CREATE,
                self::WAREHOUSES_UPDATE,
                self::WAREHOUSES_DELETE,
                self::WAREHOUSE_LOCATIONS_VIEW,
                self::WAREHOUSE_LOCATIONS_CREATE,
                self::WAREHOUSE_LOCATIONS_UPDATE,
                self::WAREHOUSE_LOCATIONS_DELETE,
                self::WAREHOUSE_BINS_VIEW,
                self::WAREHOUSE_BINS_CREATE,
                self::WAREHOUSE_BINS_UPDATE,
                self::WAREHOUSE_BINS_DELETE,
                self::WAREHOUSE_ACCESS_MANAGE,
            ],
            'Catalog' => [
                self::CATEGORIES_VIEW,
                self::CATEGORIES_CREATE,
                self::CATEGORIES_UPDATE,
                self::CATEGORIES_DELETE,
                self::UNITS_VIEW,
                self::UNITS_CREATE,
                self::UNITS_UPDATE,
                self::UNITS_DELETE,
                self::ITEMS_VIEW,
                self::ITEMS_CREATE,
                self::ITEMS_UPDATE,
                self::ITEMS_DELETE,
            ],
            'Supplier' => [
                self::SUPPLIERS_VIEW,
                self::SUPPLIERS_CREATE,
                self::SUPPLIERS_UPDATE,
                self::SUPPLIERS_DELETE,
                self::SUPPLIERS_APPROVE,
            ],
            'Procurement' => [
                self::PROCUREMENT_REQUISITIONS_VIEW,
                self::PROCUREMENT_REQUISITIONS_CREATE,
                self::PROCUREMENT_REQUISITIONS_UPDATE,
                self::PROCUREMENT_REQUISITIONS_DELETE,
                self::PROCUREMENT_REQUISITIONS_SUBMIT,
                self::PROCUREMENT_REQUISITIONS_CANCEL,
                self::PROCUREMENT_RFQS_VIEW,
                self::PROCUREMENT_RFQS_CREATE,
                self::PROCUREMENT_RFQS_UPDATE,
                self::PROCUREMENT_RFQS_DELETE,
                self::PROCUREMENT_RFQS_ISSUE,
                self::PROCUREMENT_RFQS_CANCEL,
                self::PROCUREMENT_QUOTATIONS_VIEW,
                self::PROCUREMENT_QUOTATIONS_CREATE,
                self::PROCUREMENT_QUOTATIONS_UPDATE,
                self::PROCUREMENT_QUOTATIONS_DELETE,
                self::PROCUREMENT_QUOTATIONS_SUBMIT,
                self::PROCUREMENT_QUOTATIONS_EVALUATE,
                self::PROCUREMENT_ORDERS_VIEW,
                self::PROCUREMENT_ORDERS_CREATE,
                self::PROCUREMENT_ORDERS_UPDATE,
                self::PROCUREMENT_ORDERS_DELETE,
                self::PROCUREMENT_ORDERS_APPROVE,
                self::PROCUREMENT_ORDERS_CANCEL,
                self::PROCUREMENT_ORDERS_AMEND,
                self::PROCUREMENT_ORDERS_CLOSE,
            ],
            'Receiving' => [
                self::RECEIVING_SRA_VIEW,
                self::RECEIVING_SRA_CREATE,
                self::RECEIVING_SRA_UPDATE,
                self::RECEIVING_SRA_DELETE,
                self::RECEIVING_SRA_SUBMIT,
                self::RECEIVING_SRA_CANCEL,
                self::RECEIVING_SRA_VERIFY,
                self::RECEIVING_INSPECTIONS_VIEW,
                self::RECEIVING_INSPECTIONS_START,
                self::RECEIVING_INSPECTIONS_RECORD,
                self::RECEIVING_GRN_VIEW,
                self::RECEIVING_GRN_CREATE,
                self::RECEIVING_GRN_POST,
                self::RECEIVING_GRN_CANCEL,
                self::RECEIVING_GRN_REVERSE,
                self::RECEIVING_RETURNS_VIEW,
                self::RECEIVING_RETURNS_CREATE,
                self::RECEIVING_RETURNS_UPDATE,
                self::RECEIVING_RETURNS_DELETE,
                self::RECEIVING_RETURNS_POST,
                self::RECEIVING_RETURNS_CANCEL,
            ],
            'Inventory' => [
                self::INVENTORY_BALANCES_VIEW,
                self::INVENTORY_LEDGER_VIEW,
                self::INVENTORY_TRANSACTIONS_VIEW,
            ],
            'Stocktaking' => [
                self::INVENTORY_ADJUSTMENTS_VIEW,
                self::INVENTORY_ADJUSTMENTS_CREATE,
                self::INVENTORY_ADJUSTMENTS_UPDATE,
                self::INVENTORY_ADJUSTMENTS_DELETE,
                self::INVENTORY_ADJUSTMENTS_SUBMIT,
                self::INVENTORY_ADJUSTMENTS_POST,
                self::INVENTORY_ADJUSTMENTS_REVERSE,
                self::INVENTORY_ADJUSTMENTS_CANCEL,
                self::STOCKTAKING_COUNTS_VIEW,
                self::STOCKTAKING_COUNTS_CREATE,
                self::STOCKTAKING_COUNTS_UPDATE,
                self::STOCKTAKING_COUNTS_DELETE,
                self::STOCKTAKING_COUNTS_START,
                self::STOCKTAKING_COUNTS_COUNT,
                self::STOCKTAKING_COUNTS_RECOUNT,
                self::STOCKTAKING_COUNTS_ANALYSE,
                self::STOCKTAKING_COUNTS_ADJUST,
                self::STOCKTAKING_COUNTS_COMPLETE,
                self::STOCKTAKING_COUNTS_CANCEL,
                self::STOCKTAKING_RECONCILIATIONS_VIEW,
                self::STOCKTAKING_RECONCILIATIONS_PREPARE,
                self::STOCKTAKING_RECONCILIATIONS_APPROVE,
                self::STOCKTAKING_RECONCILIATIONS_POST,
                self::STOCKTAKING_RECONCILE,
            ],
            'Distribution' => [
                self::REQUISITION_REQUESTS_VIEW,
                self::REQUISITION_REQUESTS_CREATE,
                self::REQUISITION_REQUESTS_UPDATE,
                self::REQUISITION_REQUESTS_DELETE,
                self::REQUISITION_REQUESTS_SUBMIT,
                self::REQUISITION_REQUESTS_CANCEL,
                self::REQUISITION_REQUESTS_RESERVE,
                self::REQUISITION_ISSUES_VIEW,
                self::REQUISITION_ISSUES_CREATE,
                self::REQUISITION_ISSUES_UPDATE,
                self::REQUISITION_ISSUES_DELETE,
                self::REQUISITION_ISSUES_POST,
                self::REQUISITION_ISSUES_REVERSE,
                self::REQUISITION_ISSUES_CANCEL,
                self::REQUISITION_ISSUES_ACKNOWLEDGE,
                self::DISTRIBUTION_TRANSFERS_VIEW,
                self::DISTRIBUTION_TRANSFERS_CREATE,
                self::DISTRIBUTION_TRANSFERS_UPDATE,
                self::DISTRIBUTION_TRANSFERS_DELETE,
                self::DISTRIBUTION_TRANSFERS_SUBMIT,
                self::DISTRIBUTION_TRANSFERS_CANCEL,
                self::DISTRIBUTION_TRANSFERS_DISPATCH,
                self::DISTRIBUTION_TRANSFERS_RECEIVE,
                self::DISTRIBUTION_TRANSFERS_RESOLVE,
                self::DISTRIBUTION_RETURNS_VIEW,
                self::DISTRIBUTION_RETURNS_CREATE,
                self::DISTRIBUTION_RETURNS_UPDATE,
                self::DISTRIBUTION_RETURNS_DELETE,
                self::DISTRIBUTION_RETURNS_ASSESS,
                self::DISTRIBUTION_RETURNS_POST,
                self::DISTRIBUTION_RETURNS_CANCEL,
                self::DISTRIBUTION_PROJECT_CONSUMPTION_VIEW,
            ],
            'Reporting' => [
                self::REPORTS_INVENTORY_VIEW,
                self::REPORTS_INVENTORY_EXPORT,
                self::REPORTS_PROCUREMENT_VIEW,
                self::REPORTS_PROCUREMENT_EXPORT,
                self::REPORTS_WAREHOUSE_VIEW,
                self::REPORTS_WAREHOUSE_EXPORT,
                self::REPORTS_PROJECT_VIEW,
                self::REPORTS_PROJECT_EXPORT,
                self::REPORTS_AUDIT_VIEW,
                self::REPORTS_AUDIT_EXPORT,
            ],
            'Workflow' => [
                self::WORKFLOW_FLOWS_VIEW,
                self::WORKFLOW_FLOWS_CREATE,
                self::WORKFLOW_FLOWS_UPDATE,
                self::WORKFLOW_FLOWS_DELETE,
                self::WORKFLOW_REQUESTS_VIEW,
                self::WORKFLOW_ACT,
                self::WORKFLOW_DELEGATE,
                self::WORKFLOW_ESCALATE,
                self::WORKFLOW_OVERRIDE,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        $permissions = [];

        foreach (self::groups() as $group) {
            foreach ($group as $permission) {
                $permissions[] = $permission;
            }
        }

        return array_values(array_unique($permissions));
    }

    /**
     * @return list<string>
     */
    public static function viewPermissions(): array
    {
        return array_values(array_filter(
            self::all(),
            fn (string $permission) => str_ends_with($permission, '.view'),
        ));
    }
}
