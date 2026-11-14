<?php

return [
    /*
    |---------------------------------------------------------------------------
    | Stock adjustment approval threshold
    |---------------------------------------------------------------------------
    |
    | Adjustments whose absolute value is at or above this amount require an
    | approval request. Set to 0 to auto-approve every adjustment (not
    | recommended in production). Values are compared with fixed-point maths.
    |
    */
    'adjustment_approval_threshold' => env('INVENTORY_ADJUSTMENT_APPROVAL_THRESHOLD', '1000.00'),

    /*
    |---------------------------------------------------------------------------
    | Default stocktake tolerance
    |---------------------------------------------------------------------------
    |
    | A variance breaches tolerance when either the absolute quantity or the
    | absolute value exceeds these defaults. Counts may override both values.
    |
    */
    'count_tolerance_quantity' => env('INVENTORY_COUNT_TOLERANCE_QUANTITY', '0.0000'),

    'count_tolerance_value' => env('INVENTORY_COUNT_TOLERANCE_VALUE', '0.00'),
];
