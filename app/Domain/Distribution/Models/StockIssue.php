<?php

namespace App\Domain\Distribution\Models;

use App\Domain\Inventory\Models\StockTransaction;
use App\Domain\Warehouse\Models\Warehouse;
use App\Enums\IssueStatus;
use App\Models\User;
use App\Support\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StockIssue extends Model
{
    use HasFactory, RecordsActivity;

    protected $fillable = [
        'document_number',
        'material_requisition_id',
        'warehouse_id',
        'issued_to',
        'issued_by',
        'issue_date',
        'status',
        'posted_at',
        'posted_by',
        'reversal_of_id',
        'stock_transaction_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'status' => IssueStatus::class,
            'posted_at' => 'datetime',
        ];
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(MaterialRequisition::class, 'material_requisition_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_to');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(StockTransaction::class, 'stock_transaction_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockIssueItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function acknowledgement(): HasOne
    {
        return $this->hasOne(IssueAcknowledgement::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasGlobalAccess()) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user): void {
            $query->where('issued_to', $user->id);

            $warehouseIds = $user->activeWarehouseIds();

            if ($warehouseIds !== []) {
                $query->orWhereIn('warehouse_id', $warehouseIds);
            }
        });
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term): void {
            $query->where('document_number', 'like', "%{$term}%")
                ->orWhereHas('requisition', fn (Builder $query) => $query->where('document_number', 'like', "%{$term}%"));
        });
    }

    public function canBeEdited(): bool
    {
        return $this->status === IssueStatus::Draft;
    }

    public function canBePosted(): bool
    {
        return $this->status === IssueStatus::Draft;
    }

    public function canBeReversed(): bool
    {
        return $this->status === IssueStatus::Posted;
    }

    public function canBeCancelled(): bool
    {
        return $this->status === IssueStatus::Draft;
    }

    public function isAcknowledged(): bool
    {
        return $this->acknowledgement()->exists();
    }
}
