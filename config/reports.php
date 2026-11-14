<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Export row limit
    |--------------------------------------------------------------------------
    |
    | Reports are paginated in the UI but exported in one file. The limit keeps
    | a single export inside a predictable memory footprint. Operators can raise
    | it for month-end extraction runs.
    |
    */
    'export_row_limit' => (int) env('REPORTS_EXPORT_ROW_LIMIT', 50000),

    /*
    |--------------------------------------------------------------------------
    | Analysis windows
    |--------------------------------------------------------------------------
    |
    | Default look-back for slow/non-moving stock and look-ahead for expiring
    | batches. Users may override both from the report filter bar.
    |
    */
    'slow_moving_window_days' => (int) env('REPORTS_SLOW_MOVING_DAYS', 90),

    'expiry_window_days' => (int) env('REPORTS_EXPIRY_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Scheduled snapshots
    |--------------------------------------------------------------------------
    |
    | Each snapshot stores the report total and a bounded sample of rows so a
    | trend can be reconstructed without re-running expensive aggregates.
    |
    */
    'snapshot_row_limit' => (int) env('REPORTS_SNAPSHOT_ROWS', 200),

    'snapshot_reports' => [
        'inventory.valuation',
        'inventory.reconciliation',
        'procurement.supplier_spend',
        'warehouse.utilization',
    ],
];
