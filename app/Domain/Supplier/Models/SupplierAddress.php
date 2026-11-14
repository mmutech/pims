<?php

namespace App\Domain\Supplier\Models;

use App\Enums\SupplierAddressType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierAddress extends Model
{
    protected $fillable = [
        'supplier_id',
        'type',
        'line1',
        'line2',
        'city',
        'state',
        'country',
        'postal_code',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'type' => SupplierAddressType::class,
            'is_primary' => 'boolean',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
