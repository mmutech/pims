<?php

namespace App\Domain\Warehouse\Models;

use App\Domain\Organization\Models\BusinessUnit;
use App\Enums\WarehouseAccessLevel;
use App\Enums\WarehouseType;
use App\Models\User;
use App\Support\Concerns\HasImmutableCode;
use App\Support\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use HasFactory, HasImmutableCode, RecordsActivity, SoftDeletes;

    protected $fillable = [
        'business_unit_id',
        'code',
        'name',
        'type',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'phone',
        'email',
        'manager_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => WarehouseType::class,
            'is_active' => 'boolean',
        ];
    }

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(WarehouseLocation::class);
    }

    public function bins(): HasManyThrough
    {
        return $this->hasManyThrough(WarehouseBin::class, WarehouseLocation::class, 'warehouse_id', 'warehouse_location_id');
    }

    public function accessGrants(): HasMany
    {
        return $this->hasMany(UserWarehouseAccess::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_warehouse_access')
            ->withPivot(['access_level', 'is_primary', 'granted_by', 'granted_at', 'revoked_at'])
            ->withTimestamps();
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
                ->orWhere('city', 'like', "%{$term}%");
        });
    }

    public function hasActiveLocations(): bool
    {
        return $this->locations()->where('is_active', true)->exists();
    }

    public function managerHasAccess(): bool
    {
        if ($this->manager_id === null) {
            return false;
        }

        return $this->accessGrants()
            ->where('user_id', $this->manager_id)
            ->whereNull('revoked_at')
            ->where('access_level', WarehouseAccessLevel::Manage->value)
            ->exists();
    }
}
