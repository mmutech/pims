<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Unsourced purchase orders
    |--------------------------------------------------------------------------
    |
    | When false, every purchase order must trace to an accepted supplier
    | quotation or an approved purchase requisition. Enable only for
    | documented emergency procurement with compensating controls.
    |
    */
    'allow_unsourced_orders' => env('PROCUREMENT_ALLOW_UNSOURCED_ORDERS', false),
];
