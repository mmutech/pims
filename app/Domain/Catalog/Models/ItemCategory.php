<?php

namespace App\Domain\Catalog\Models;

use App\Support\Concerns\HasImmutableCode;
use App\Support\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItemCategory extends Model
{
    use HasFactory, HasImmutableCode, RecordsActivity, SoftDeletes;

    protected $fillable = [
        'parent_id',
        'code',
        'name',
        'description',
        'track_batches',
        'track_serials',
        'requires_quality_inspection',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'track_batches' => 'boolean',
            'track_serials' => 'boolean',
            'requires_quality_inspection' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'category_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term): void {
            $query->where('code', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%");
        });
    }

    public function depth(): int
    {
        $depth = 0;
        $parent = $this->parent;

        while ($parent !== null && $depth < 10) {
            $depth++;
            $parent = $parent->parent;
        }

        return $depth;
    }

    public function isDescendantOf(self $candidate): bool
    {
        $parent = $this->parent;

        while ($parent !== null) {
            if ($parent->is($candidate)) {
                return true;
            }

            $parent = $parent->parent;
        }

        return false;
    }
}
