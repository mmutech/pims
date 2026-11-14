<?php

namespace App\Domain\Workflow\Models;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalDelegation extends Model
{
    use HasFactory;

    protected $fillable = [
        'delegator_id',
        'delegate_id',
        'document_type',
        'starts_at',
        'ends_at',
        'reason',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function delegator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegator_id');
    }

    public function delegate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegate_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $at ??= now();

        return $query->where('is_active', true)
            ->where('starts_at', '<=', $at)
            ->where('ends_at', '>=', $at);
    }

    public function scopeForDocumentType(Builder $query, string $documentType): Builder
    {
        return $query->where(function (Builder $query) use ($documentType): void {
            $query->whereNull('document_type')
                ->orWhere('document_type', $documentType);
        });
    }

    public function isActiveAt(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        return $this->is_active
            && $this->starts_at->lessThanOrEqualTo($at)
            && $this->ends_at->greaterThanOrEqualTo($at);
    }
}
