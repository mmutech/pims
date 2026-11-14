<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\ItemBatch;
use App\Domain\Catalog\Models\ItemCategory;
use App\Domain\Catalog\Models\ItemSerialNumber;
use App\Domain\Catalog\Models\UnitOfMeasure;
use App\DTOs\Catalog\CategoryData;
use App\DTOs\Catalog\ItemData;
use App\DTOs\Catalog\UnitData;
use App\Enums\BatchStatus;
use App\Support\Decimal;
use DomainException;
use Illuminate\Support\Facades\DB;

class CatalogService
{
    public function createCategory(CategoryData $data): ItemCategory
    {
        $this->validateCategoryParent($data->parentId, null);

        return ItemCategory::create($data->toArray());
    }

    public function updateCategory(ItemCategory $category, CategoryData $data): ItemCategory
    {
        if ($category->code !== $data->code) {
            throw new DomainException('The category code is immutable once created.');
        }

        $this->validateCategoryParent($data->parentId, $category);

        if (! $data->isActive && $category->items()->where('is_active', true)->exists()) {
            throw new DomainException('Category cannot be deactivated while active items exist.');
        }

        $category->update($data->toArray());

        return $category->refresh();
    }

    public function deleteCategory(ItemCategory $category): void
    {
        if ($category->children()->exists()) {
            throw new DomainException('Category cannot be deleted while subcategories exist.');
        }

        if ($category->items()->exists()) {
            throw new DomainException('Category cannot be deleted while items reference it.');
        }

        $category->delete();
    }

    public function createUnit(UnitData $data): UnitOfMeasure
    {
        $this->validateUnit($data);

        return UnitOfMeasure::create($data->toArray());
    }

    public function updateUnit(UnitOfMeasure $unit, UnitData $data): UnitOfMeasure
    {
        if ($unit->code !== $data->code) {
            throw new DomainException('The unit code is immutable once created.');
        }

        $this->validateUnit($data);

        if (! $data->isActive && $unit->items()->where('is_active', true)->exists()) {
            throw new DomainException('Unit cannot be deactivated while active items use it.');
        }

        $unit->update($data->toArray());

        return $unit->refresh();
    }

    public function deleteUnit(UnitOfMeasure $unit): void
    {
        if ($unit->items()->exists() || $unit->itemUnits()->exists()) {
            throw new DomainException('Unit cannot be deleted while items reference it.');
        }

        $unit->delete();
    }

    public function createItem(ItemData $data): Item
    {
        $this->validateItem($data);

        return DB::transaction(function () use ($data): Item {
            $item = Item::create($data->toArray());
            $this->syncSpecifications($item, $data->specifications);
            $this->syncUnits($item, $data->units);

            return $item;
        });
    }

    public function updateItem(Item $item, ItemData $data): Item
    {
        if ($item->code !== $data->code) {
            throw new DomainException('The item code is immutable once created.');
        }

        $this->validateItem($data);

        if ($item->tracking_type !== $data->trackingType && ($item->batches()->exists() || $item->serialNumbers()->exists())) {
            throw new DomainException('Tracking type cannot change once batches or serial numbers exist.');
        }

        return DB::transaction(function () use ($item, $data): Item {
            $item->update($data->toArray());
            $this->syncSpecifications($item, $data->specifications);
            $this->syncUnits($item, $data->units);

            return $item->refresh();
        });
    }

    public function deleteItem(Item $item): void
    {
        if ($item->batches()->exists() || $item->serialNumbers()->exists()) {
            throw new DomainException('Item cannot be deleted while batches or serial numbers exist.');
        }

        $item->delete();
    }

    public function createBatch(Item $item, string $batchNumber, ?string $manufactureDate, ?string $expiryDate, ?int $supplierId = null): ItemBatch
    {
        if ($manufactureDate !== null && $expiryDate !== null && strtotime($expiryDate) < strtotime($manufactureDate)) {
            throw new DomainException('Batch expiry date must be on or after the manufacture date.');
        }

        return $item->batches()->create([
            'batch_number' => trim($batchNumber),
            'manufacture_date' => $manufactureDate,
            'expiry_date' => $expiryDate,
            'supplier_id' => $supplierId,
            'status' => BatchStatus::Active->value,
        ]);
    }

    public function createSerial(Item $item, string $serialNumber, ?ItemBatch $batch = null, ?int $warehouseId = null): ItemSerialNumber
    {
        if ($item->serialNumbers()->where('serial_number', $serialNumber)->exists()) {
            throw new DomainException('Serial number already exists.');
        }

        return $item->serialNumbers()->create([
            'batch_id' => $batch?->id,
            'serial_number' => trim($serialNumber),
            'current_warehouse_id' => $warehouseId,
        ]);
    }

    /**
     * @param  list<array{attribute: string, value: string, unit_of_measure_id?: int|null}>  $specifications
     */
    public function syncSpecifications(Item $item, array $specifications): void
    {
        $item->specifications()->delete();

        foreach (array_values($specifications) as $index => $specification) {
            if (blank($specification['attribute'] ?? null)) {
                continue;
            }

            $item->specifications()->create([
                'attribute' => trim((string) $specification['attribute']),
                'value' => (string) ($specification['value'] ?? ''),
                'unit_of_measure_id' => $specification['unit_of_measure_id'] ?? null,
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @param  list<array{unit_of_measure_id: int, conversion_factor: string, is_base?: bool}>  $units
     */
    public function syncUnits(Item $item, array $units): void
    {
        $item->units()->delete();

        $item->units()->create([
            'unit_of_measure_id' => $item->base_unit_of_measure_id,
            'conversion_factor' => '1.000000',
            'is_base' => true,
        ]);

        $baseUnits = $item->units()->pluck('unit_of_measure_id')->all();

        foreach ($units as $unit) {
            $unitId = (int) ($unit['unit_of_measure_id'] ?? 0);

            if ($unitId === 0 || in_array($unitId, $baseUnits, true)) {
                continue;
            }

            $factor = (string) ($unit['conversion_factor'] ?? '1');

            if (! Decimal::isPositive($factor, Decimal::COST_SCALE)) {
                throw new DomainException('Unit conversion factors must be greater than zero.');
            }

            $item->units()->create([
                'unit_of_measure_id' => $unitId,
                'conversion_factor' => $factor,
                'is_base' => false,
            ]);
        }
    }

    private function validateCategoryParent(?int $parentId, ?ItemCategory $category): void
    {
        if ($parentId === null) {
            return;
        }

        if ($category !== null && $parentId === $category->id) {
            throw new DomainException('A category cannot be its own parent.');
        }

        $parent = ItemCategory::find($parentId);

        if ($parent === null) {
            throw new DomainException('The selected parent category does not exist.');
        }

        if ($category !== null && $parent->isDescendantOf($category)) {
            throw new DomainException('A category cannot be nested under one of its descendants.');
        }

        if ($parent->depth() >= 4) {
            throw new DomainException('Category nesting is limited to five levels.');
        }
    }

    private function validateUnit(UnitData $data): void
    {
        if ($data->decimalPlaces < 0 || $data->decimalPlaces > 6) {
            throw new DomainException('Unit decimal places must be between 0 and 6.');
        }
    }

    private function validateItem(ItemData $data): void
    {
        if (! Decimal::isPositive($data->standardCost, Decimal::QUANTITY_SCALE) && ! Decimal::isZero($data->standardCost, Decimal::QUANTITY_SCALE)) {
            throw new DomainException('Standard cost cannot be negative.');
        }

        if ($data->maximumStock !== null && Decimal::compare($data->maximumStock, $data->minimumStock, Decimal::QUANTITY_SCALE) < 0) {
            throw new DomainException('Maximum stock must be greater than or equal to minimum stock.');
        }

        if (Decimal::isNegative($data->reorderLevel, Decimal::QUANTITY_SCALE)) {
            throw new DomainException('Reorder level cannot be negative.');
        }

        if (Decimal::isNegative($data->reorderQuantity, Decimal::QUANTITY_SCALE)) {
            throw new DomainException('Reorder quantity cannot be negative.');
        }

        if ($data->shelfLifeDays !== null && $data->shelfLifeDays < 0) {
            throw new DomainException('Shelf life days cannot be negative.');
        }

        $unitExists = UnitOfMeasure::whereKey($data->baseUnitOfMeasureId)->exists();

        if (! $unitExists) {
            throw new DomainException('The selected base unit of measure does not exist.');
        }
    }
}
