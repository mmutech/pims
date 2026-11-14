<?php

namespace App\Domain\Supplier\Models;

use App\Domain\Catalog\Models\ItemBatch;
use App\Enums\SupplierStatus;
use App\Models\User;
use App\Support\Concerns\HasImmutableCode;
use App\Support\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, HasImmutableCode, RecordsActivity, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'legal_name',
        'tax_identification_number',
        'registration_number',
        'email',
        'phone',
        'website',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'contact_person',
        'status',
        'is_approved',
        'approved_at',
        'approved_by',
        'created_by',
        'rating',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => SupplierStatus::class,
            'is_approved' => 'boolean',
            'approved_at' => 'datetime',
            'rating' => 'decimal:2',
        ];
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(SupplierContact::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(SupplierAddress::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SupplierDocument::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(SupplierEvaluation::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ItemBatch::class);
    }

    public function scopeOperational(Builder $query): Builder
    {
        return $query->where('status', SupplierStatus::Active->value);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('is_approved', true)
            ->where('status', SupplierStatus::Active->value);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term): void {
            $query->where('code', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%")
                ->orWhere('tax_identification_number', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%");
        });
    }

    public function canReceiveAwards(): bool
    {
        return $this->is_approved && $this->status->canReceiveAwards();
    }
}
