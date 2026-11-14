<?php

namespace App\Domain\Stocktaking\Models;

use App\Enums\VarianceResolution;
use App\Enums\VarianceType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCountVariance extends Model
{
    protected $fillable = [
        'stock_count_id',
        'stock_count_item_id',
        'stock_adjustment_id',
        'variance_type',
        'quantity',
        'value',
        'tolerance_breached',
        'resolution',
        'investigated_by',
        'investigated_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'variance_type' => VarianceType::class,
            'quantity' => 'decimal:4',
            'value' => 'decimal:2',
            'tolerance_breached' => 'boolean',
            'resolution' => VarianceResolution::class,
            'investigated_at' => 'datetime',
        ];
    }

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }

    public function stockCountItem(): BelongsTo
    {
        return $this->belongsTo(StockCountItem::class);
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
    }

    public function investigator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investigated_by');
    }

    public function isOpen(): bool
    {
        return $this->resolution->isOpen();
    }
}
