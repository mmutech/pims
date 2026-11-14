<?php

namespace App\Domain\Distribution\Models;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\ItemBatch;
use App\Domain\Catalog\Models\UnitOfMeasure;
use App\Domain\Inventory\Models\StockDimension;
use App\Domain\Inventory\Models\StockTransactionItem;
use App\Enums\MaterialCondition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_return_id',
        'item_id',
        'unit_of_measure_id',
        'returned_quantity',
        'accepted_quantity',
        'rejected_quantity',
        'condition',
        'batch_id',
        'serial_number_id',
        'unit_cost',
        'stock_dimension_id',
        'stock_transaction_item_id',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'returned_quantity' => 'decimal:4',
            'accepted_quantity' => 'decimal:4',
            'rejected_quantity' => 'decimal:4',
            'condition' => MaterialCondition::class,
            'unit_cost' => 'decimal:6',
            'sort_order' => 'integer',
        ];
    }

    public function materialReturn(): BelongsTo
    {
        return $this->belongsTo(MaterialReturn::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ItemBatch::class);
    }

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(StockDimension::class, 'stock_dimension_id');
    }

    public function transactionItem(): BelongsTo
    {
        return $this->belongsTo(StockTransactionItem::class, 'stock_transaction_item_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(MaterialReturnAssessment::class);
    }

    public function latestAssessment(): ?MaterialReturnAssessment
    {
        return $this->assessments()->latest('id')->first();
    }
}
