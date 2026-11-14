<?php

namespace App\Domain\Catalog\Models;

use App\Enums\ItemTrackingType;
use App\Enums\ValuationMethod;
use App\Support\Concerns\HasImmutableCode;
use App\Support\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasFactory, HasImmutableCode, RecordsActivity, SoftDeletes;

    protected $fillable = [
        'category_id',
        'code',
        'name',
        'description',
        'brand',
        'model',
        'manufacturer_part_number',
        'tracking_type',
        'base_unit_of_measure_id',
        'minimum_stock',
        'maximum_stock',
        'reorder_level',
        'reorder_quantity',
        'standard_cost',
        'valuation_method',
        'requires_quality_inspection',
        'shelf_life_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tracking_type' => ItemTrackingType::class,
            'valuation_method' => ValuationMethod::class,
            'minimum_stock' => 'decimal:4',
            'maximum_stock' => 'decimal:4',
            'reorder_level' => 'decimal:4',
            'reorder_quantity' => 'decimal:4',
            'standard_cost' => 'decimal:4',
            'requires_quality_inspection' => 'boolean',
            'shelf_life_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'category_id');
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'base_unit_of_measure_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(ItemUnit::class);
    }

    public function specifications(): HasMany
    {
        return $this->hasMany(ItemSpecification::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ItemBatch::class);
    }

    public function serialNumbers(): HasMany
    {
        return $this->hasMany(ItemSerialNumber::class);
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
                ->orWhere('name', 'like', "%{$term}%")
                ->orWhere('brand', 'like', "%{$term}%")
                ->orWhere('manufacturer_part_number', 'like', "%{$term}%");
        });
    }

    public function tracksBatches(): bool
    {
        return $this->tracking_type->requiresBatch();
    }

    public function tracksSerials(): bool
    {
        return $this->tracking_type->requiresSerial();
    }
}
