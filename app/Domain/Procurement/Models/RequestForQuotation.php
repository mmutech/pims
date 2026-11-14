<?php

namespace App\Domain\Procurement\Models;

use App\Domain\Organization\Models\BusinessUnit;
use App\Enums\RequestForQuotationStatus;
use App\Enums\SupplierQuotationStatus;
use App\Models\User;
use App\Support\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class RequestForQuotation extends Model
{
    use HasFactory, RecordsActivity, SoftDeletes;

    protected $fillable = [
        'document_number',
        'purchase_requisition_id',
        'business_unit_id',
        'title',
        'description',
        'closing_date',
        'status',
        'issued_at',
        'closed_at',
        'awarded_at',
        'cancelled_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => RequestForQuotationStatus::class,
            'closing_date' => 'date',
            'issued_at' => 'datetime',
            'closed_at' => 'datetime',
            'awarded_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function purchaseRequisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class);
    }

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RfqItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(RfqSupplier::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(SupplierQuotation::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(QuotationEvaluation::class);
    }

    public function latestEvaluation(): HasOne
    {
        return $this->hasOne(QuotationEvaluation::class)->latestOfMany();
    }

    public function acceptedQuotation(): HasOne
    {
        return $this->hasOne(SupplierQuotation::class)
            ->where('status', SupplierQuotationStatus::Accepted->value)
            ->latestOfMany();
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
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
                ->orWhere('title', 'like', "%{$term}%");
        });
    }

    public function canBeEdited(): bool
    {
        return $this->status === RequestForQuotationStatus::Draft;
    }

    public function canBeIssued(): bool
    {
        return $this->status === RequestForQuotationStatus::Draft;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            RequestForQuotationStatus::Draft,
            RequestForQuotationStatus::Issued,
            RequestForQuotationStatus::Closed,
        ], true);
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }
}
