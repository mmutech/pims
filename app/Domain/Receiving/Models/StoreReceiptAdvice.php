<?php

namespace App\Domain\Receiving\Models;

use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\Supplier\Models\Supplier;
use App\Domain\Warehouse\Models\Warehouse;
use App\Enums\QualityInspectionStatus;
use App\Enums\ReceiptStatus;
use App\Models\User;
use App\Support\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoreReceiptAdvice extends Model
{
    use HasFactory, RecordsActivity, SoftDeletes;

    protected $table = 'store_receipt_advices';

    protected $fillable = [
        'document_number',
        'purchase_order_id',
        'warehouse_id',
        'supplier_id',
        'expected_delivery_date',
        'vehicle_number',
        'driver_name',
        'driver_phone',
        'delivery_note_number',
        'status',
        'received_by',
        'verified_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReceiptStatus::class,
            'expected_delivery_date' => 'date',
            'verified_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StoreReceiptAdviceItem::class, 'sra_id')->orderBy('sort_order')->orderBy('id');
    }

    public function verification(): HasOne
    {
        return $this->hasOne(DeliveryVerification::class, 'sra_id');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(QualityInspection::class, 'sra_id')->latest('id');
    }

    public function goodsReceivedNotes(): HasMany
    {
        return $this->hasMany(GoodsReceivedNote::class, 'sra_id')->latest('id');
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

            $query->orWhere('received_by', $user->id);
        });
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term): void {
            $query->where('document_number', 'like', "%{$term}%")
                ->orWhere('delivery_note_number', 'like', "%{$term}%")
                ->orWhereHas('supplier', fn (Builder $query) => $query->where('name', 'like', "%{$term}%"))
                ->orWhereHas('purchaseOrder', fn (Builder $query) => $query->where('document_number', 'like', "%{$term}%"));
        });
    }

    public function canBeEdited(): bool
    {
        return $this->status === ReceiptStatus::Draft;
    }

    public function canBeSubmitted(): bool
    {
        return $this->status === ReceiptStatus::Draft;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            ReceiptStatus::Draft,
            ReceiptStatus::PendingVerification,
            ReceiptStatus::Verified,
            ReceiptStatus::PendingInspection,
            ReceiptStatus::Inspected,
        ], true);
    }

    public function canBeVerified(): bool
    {
        return $this->status === ReceiptStatus::PendingVerification;
    }

    public function canBeInspected(): bool
    {
        return $this->status === ReceiptStatus::PendingInspection;
    }

    public function canBeReceived(): bool
    {
        return in_array($this->status, [ReceiptStatus::Verified, ReceiptStatus::Inspected], true);
    }

    /**
     * True when at least one line references an item that mandates inspection.
     */
    public function requiresQualityInspection(): bool
    {
        return $this->items()->with('item')->get()
            ->contains(fn (StoreReceiptAdviceItem $item) => (bool) $item->item?->requires_quality_inspection);
    }

    public function completedInspection(): ?QualityInspection
    {
        return $this->inspections()
            ->whereIn('status', [
                QualityInspectionStatus::Passed->value,
                QualityInspectionStatus::Failed->value,
                QualityInspectionStatus::PartiallyPassed->value,
            ])
            ->first();
    }
}
