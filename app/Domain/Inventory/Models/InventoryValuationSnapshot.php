<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Catalog\Models\Item;
use App\Domain\Warehouse\Models\Warehouse;
use App\Enums\ValuationMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryValuationSnapshot extends Model
{
    protected $fillable = [
        'snapshot_date',
        'warehouse_id',
        'item_id',
        'quantity',
        'unit_cost',
        'total_value',
        'valuation_method',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:6',
            'total_value' => 'decimal:2',
            'valuation_method' => ValuationMethod::class,
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
