<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Warehouse\Models\Warehouse;
use App\Enums\SerialStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemSerialNumber extends Model
{
    protected $fillable = [
        'item_id',
        'batch_id',
        'serial_number',
        'status',
        'current_warehouse_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => SerialStatus::class,
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ItemBatch::class, 'batch_id');
    }

    public function currentWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'current_warehouse_id');
    }
}
