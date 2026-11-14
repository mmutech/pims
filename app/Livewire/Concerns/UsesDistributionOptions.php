<?php

namespace App\Livewire\Concerns;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\UnitOfMeasure;
use App\Domain\Organization\Models\BusinessUnit;
use App\Domain\Organization\Models\CostCenter;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Project;
use App\Domain\Warehouse\Models\Warehouse;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Shared option lists for distribution screens. Business units only remain as
 * a warehouse grouping, so master data is no longer filtered by it.
 */
trait UsesDistributionOptions
{
    /**
     * @return Collection<int, Warehouse>
     */
    protected function accessibleWarehouses()
    {
        $query = Warehouse::query()->where('is_active', true)->orderBy('name');

        if (! auth()->user()->hasGlobalAccess()) {
            $query->whereIn('id', auth()->user()->activeWarehouseIds());
        }

        return $query->get();
    }

    /**
     * @return Collection<int, BusinessUnit>
     */
    protected function scopedBusinessUnits()
    {
        return BusinessUnit::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Department>
     */
    protected function scopedDepartments()
    {
        return Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, CostCenter>
     */
    protected function scopedCostCenters()
    {
        return CostCenter::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Project>
     */
    protected function scopedProjects()
    {
        $user = auth()->user();
        $query = Project::query()->where('is_active', true)->orderBy('name');

        if ($user->hasGlobalAccess()) {
            return $query->get();
        }

        return $query->where(function (Builder $query) use ($user): void {
            $query->where('manager_id', $user->id)
                ->orWhereHas('members', fn (Builder $query) => $query
                    ->where('user_id', $user->id)
                    ->where('is_active', true));
        })->get();
    }

    /**
     * @return Collection<int, User>
     */
    protected function scopedUsers()
    {
        $user = auth()->user();
        $query = User::query()
            ->where('status', UserStatus::Active->value)
            ->orderBy('name');

        if ($user->hasGlobalAccess()) {
            return $query->get();
        }

        $warehouseIds = $user->activeWarehouseIds();

        return $query->where(function (Builder $query) use ($user, $warehouseIds): void {
            $query->whereKey($user->id);

            if ($warehouseIds !== []) {
                $query->orWhereHas('warehouseAccess', function (Builder $query) use ($warehouseIds): void {
                    $query->whereNull('revoked_at')->whereIn('warehouse_id', $warehouseIds);
                });
            }
        })->get();
    }

    /**
     * @return Collection<int, Item>
     */
    protected function catalogItems()
    {
        return Item::query()
            ->with('baseUnit')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, UnitOfMeasure>
     */
    protected function unitOptions()
    {
        return UnitOfMeasure::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
