<?php

namespace App\Domain\Receiving\Models;

use App\Domain\Inventory\Models\StockTransaction;
use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\Supplier\Models\Supplier;
use App\Domain\Warehouse\Models\Warehouse;
use App\Enums\GoodsReceivedNoteStatus;
use App\Models\User;
use App\Support\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceivedNote extends Model
{
    use HasFactory, RecordsActivity;

    protected $fillable = [
        'document_number',
        'purchase_order_id',
        'sra_id',
        'quality_inspection_id',
        'warehouse_id',
        'supplier_id',
        'received_date',
        'status',
        'posted_at',
        'posted_by',
        'reversal_of_id',
        'stock_transaction_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => GoodsReceivedNoteStatus::class,
            'received_date' => 'date',
            'posted_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function sra(): BelongsTo
    {
        return $this->belongsTo(StoreReceiptAdvice::class, 'sra_id');
    }

    public function qualityInspection(): BelongsTo
    {
        return $this->belongsTo(QualityInspection::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceivedNoteItem::class, 'grn_id')->orderBy('sort_order')->orderBy('id');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function stockTransaction(): BelongsTo
    {
        return $this->belongsTo(StockTransaction::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasGlobalAccess()) {
            return $query;
        }

        $warehouseIds = $user->activeWarehouseIds();

        return $query->where(function (Builder $query) use ($warehouseIds, $user): void {
            if ($warehouseIds !== []) {
                $query->whereIn('warehouse_id', $warehouseIds);
            }

            $query->orWhere('created_by', $user->id);
        });
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term): void {
            $query->where('document_number', 'like', "%{$term}%")
                ->orWhereHas('supplier', fn (Builder $query) => $query->where('name', 'like', "%{$term}%"))
                ->orWhereHas('purchaseOrder', fn (Builder $query) => $query->where('document_number', 'like', "%{$term}%"));
        });
    }

    public function isPosted(): bool
    {
        return $this->status === GoodsReceivedNoteStatus::Posted;
    }

    public function canBePosted(): bool
    {
        return $this->status === GoodsReceivedNoteStatus::Draft;
    }

    public function canBeCancelled(): bool
    {
        return $this->status === GoodsReceivedNoteStatus::Draft;
    }

    public function canBeReversed(): bool
    {
        return $this->status === GoodsReceivedNoteStatus::Posted;
    }
}
