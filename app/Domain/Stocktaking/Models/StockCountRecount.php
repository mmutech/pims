<?php

namespace App\Domain\Stocktaking\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCountRecount extends Model
{
    protected $fillable = [
        'stock_count_item_id',
        'recount_number',
        'counted_quantity',
        'counted_by',
        'counted_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'recount_number' => 'integer',
            'counted_quantity' => 'decimal:4',
            'counted_at' => 'datetime',
        ];
    }

    public function stockCountItem(): BelongsTo
    {
        return $this->belongsTo(StockCountItem::class);
    }

    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }
}
