<?php

namespace App\Domain\Receiving\Models;

use App\Domain\Supplier\Models\Supplier;
use App\Domain\Warehouse\Models\Warehouse;
use App\Enums\ReturnStatus;
use App\Models\User;
use App\Support\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierReturn extends Model
{
    use HasFactory, RecordsActivity;

    protected $fillable = [
        'document_number',
        'supplier_id',
        'grn_id',
        'warehouse_id',
        'reason',
        'status',
        'return_date',
        'credit_note_number',
        'posted_at',
        'posted_by',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReturnStatus::class,
            'return_date' => 'date',
            'posted_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function grn(): BelongsTo
    {
        return $this->belongsTo(GoodsReceivedNote::class, 'grn_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierReturnItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
                ->orWhere('credit_note_number', 'like', "%{$term}%")
                ->orWhereHas('supplier', fn (Builder $query) => $query->where('name', 'like', "%{$term}%"));
        });
    }

    public function canBeEdited(): bool
    {
        return $this->status === ReturnStatus::Draft;
    }

    public function canBePosted(): bool
    {
        return in_array($this->status, [ReturnStatus::Draft, ReturnStatus::Assessed], true);
    }

    public function canBeCancelled(): bool
    {
        return $this->status === ReturnStatus::Draft;
    }
}
