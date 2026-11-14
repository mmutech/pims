<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\InventoryValuationSnapshot;
use App\Enums\ValuationMethod;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Stores a point-in-time valuation projection per warehouse and item.
 *
 * Snapshots are derived from inventory_balances and are safe to regenerate:
 * rerunning the same date replaces the existing row for that warehouse/item.
 */
class InventoryValuationService
{
    /**
     * @return int number of snapshot rows written
     */
    public function snapshot(Carbon|string $date, ?int $warehouseId = null, ?User $actor = null): int
    {
        $snapshotDate = $date instanceof Carbon
            ? $date->toDateString()
            : Carbon::parse($date)->toDateString();

        return DB::transaction(function () use ($snapshotDate, $warehouseId, $actor): int {
            $balances = InventoryBalance::query()
                ->with('item')
                ->when($warehouseId !== null, fn ($query) => $query->where('warehouse_id', $warehouseId))
                ->where('on_hand_quantity', '>', 0)
                ->orderBy('warehouse_id')
                ->orderBy('item_id')
                ->get();

            $groups = $balances->groupBy(
                fn (InventoryBalance $balance): string => $balance->warehouse_id.'|'.$balance->item_id,
            );

            $written = 0;

            foreach ($groups as $group) {
                /** @var InventoryBalance $first */
                $first = $group->first();

                $quantity = $group->reduce(
                    fn (string $carry, InventoryBalance $balance): string => Decimal::add(
                        $carry,
                        (string) $balance->on_hand_quantity,
                        Decimal::QUANTITY_SCALE,
                    ),
                    Decimal::normalize('0'),
                );

                $value = $group->reduce(
                    fn (string $carry, InventoryBalance $balance): string => Decimal::add(
                        $carry,
                        (string) $balance->total_value,
                        Decimal::MONEY_SCALE,
                    ),
                    Decimal::normalize('0', Decimal::MONEY_SCALE),
                );

                $unitCost = Decimal::isPositive($quantity)
                    ? Decimal::divide($value, $quantity, Decimal::COST_SCALE)
                    : Decimal::normalize('0', Decimal::COST_SCALE);

                InventoryValuationSnapshot::query()->updateOrCreate(
                    [
                        'snapshot_date' => $snapshotDate,
                        'warehouse_id' => $first->warehouse_id,
                        'item_id' => $first->item_id,
                    ],
                    [
                        'quantity' => $quantity,
                        'unit_cost' => $unitCost,
                        'total_value' => $value,
                        'valuation_method' => $first->item?->valuation_method ?? ValuationMethod::WeightedAverage,
                        'created_by' => $actor?->id,
                    ],
                );

                $written++;
            }

            return $written;
        });
    }
}
