<?php

namespace App\Domain\Procurement\Models;

use App\Domain\Supplier\Models\Supplier;
use App\Enums\SupplierQuotationStatus;
use App\Models\User;
use App\Support\Concerns\RecordsActivity;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierQuotation extends Model
{
    use HasFactory, RecordsActivity, SoftDeletes;

    protected $fillable = [
        'document_number',
        'request_for_quotation_id',
        'supplier_id',
        'currency',
        'valid_until',
        'lead_time_days',
        'payment_terms',
        'warranty_terms',
        'subtotal',
        'tax_amount',
        'total_amount',
        'status',
        'submitted_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => SupplierQuotationStatus::class,
            'valid_until' => 'date',
            'lead_time_days' => 'integer',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'submitted_at' => 'datetime',
        ];
    }

    public function requestForQuotation(): BelongsTo
    {
        return $this->belongsTo(RequestForQuotation::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierQuotationItem::class);
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

        return $query->where('created_by', $user->id);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term): void {
            $query->where('document_number', 'like', "%{$term}%")
                ->orWhereHas('supplier', fn (Builder $query) => $query->where('name', 'like', "%{$term}%"));
        });
    }

    public function canBeEdited(): bool
    {
        return $this->status === SupplierQuotationStatus::Draft;
    }

    public function canBeSubmitted(): bool
    {
        return in_array($this->status, [SupplierQuotationStatus::Draft, SupplierQuotationStatus::Withdrawn], true);
    }

    public function canBeWithdrawn(): bool
    {
        return $this->status === SupplierQuotationStatus::Submitted;
    }

    /**
     * Recalculate subtotal, tax, and total using fixed precision.
     */
    public function recalculateTotals(): void
    {
        $subtotal = '0';
        $tax = '0';

        foreach ($this->items()->get() as $item) {
            $subtotal = Decimal::add($subtotal, $item->line_total, Decimal::MONEY_SCALE);

            $lineTax = Decimal::multiply(
                Decimal::subtract($item->line_total, $item->discount_amount, Decimal::MONEY_SCALE),
                Decimal::divide($item->tax_rate, '100', Decimal::COST_SCALE),
                Decimal::MONEY_SCALE,
            );

            $tax = Decimal::add($tax, $lineTax, Decimal::MONEY_SCALE);
        }

        $this->forceFill([
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'total_amount' => Decimal::add($subtotal, $tax, Decimal::MONEY_SCALE),
        ])->save();
    }
}
