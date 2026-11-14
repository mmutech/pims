<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum SupplierAddressType: string
{
    use EnumHelpers;

    case Registered = 'registered';
    case Billing = 'billing';
    case Shipping = 'shipping';
    case Warehouse = 'warehouse';
}
