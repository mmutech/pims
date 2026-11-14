<?php

namespace App\DTOs\Catalog;

use App\Enums\ItemTrackingType;
use App\Enums\ValuationMethod;
use App\Support\Input;
use Illuminate\Support\Arr;

final readonly class ItemData
{
    /**
     * @param  list<array{attribute: string, value: string, unit_of_measure_id?: int|null}>  $specifications
     * @param  list<array{unit_of_measure_id: int, conversion_factor: string, is_base?: bool}>  $units
     */
    public function __construct(
        public int $categoryId,
        public string $code,
        public string $name,
        public int $baseUnitOfMeasureId,
        public ItemTrackingType $trackingType = ItemTrackingType::None,
        public ?string $description = null,
        public ?string $brand = null,
        public ?string $model = null,
        public ?string $manufacturerPartNumber = null,
        public string $minimumStock = '0.0000',
        public ?string $maximumStock = null,
        public string $reorderLevel = '0.0000',
        public string $reorderQuantity = '0.0000',
        public string $standardCost = '0.0000',
        public ValuationMethod $valuationMethod = ValuationMethod::WeightedAverage,
        public bool $requiresQualityInspection = true,
        public ?int $shelfLifeDays = null,
        public bool $isActive = true,
        public array $specifications = [],
        public array $units = [],
    ) {}

    public static function fromArray(array $data): self
    {
        $tracking = Arr::get($data, 'tracking_type', ItemTrackingType::None->value);
        $valuation = Arr::get($data, 'valuation_method', ValuationMethod::WeightedAverage->value);

        return new self(
            categoryId: (int) Arr::get($data, 'category_id'),
            code: strtoupper(trim((string) Arr::get($data, 'code'))),
            name: trim((string) Arr::get($data, 'name')),
            baseUnitOfMeasureId: (int) Arr::get($data, 'base_unit_of_measure_id'),
            trackingType: $tracking instanceof ItemTrackingType ? $tracking : ItemTrackingType::from((string) $tracking),
            description: Arr::get($data, 'description'),
            brand: Arr::get($data, 'brand'),
            model: Arr::get($data, 'model'),
            manufacturerPartNumber: Arr::get($data, 'manufacturer_part_number'),
            minimumStock: (string) Arr::get($data, 'minimum_stock', '0.0000'),
            maximumStock: Input::stringOrNull(Arr::get($data, 'maximum_stock')),
            reorderLevel: (string) Arr::get($data, 'reorder_level', '0.0000'),
            reorderQuantity: (string) Arr::get($data, 'reorder_quantity', '0.0000'),
            standardCost: (string) Arr::get($data, 'standard_cost', '0.0000'),
            valuationMethod: $valuation instanceof ValuationMethod ? $valuation : ValuationMethod::from((string) $valuation),
            requiresQualityInspection: (bool) Arr::get($data, 'requires_quality_inspection', true),
            shelfLifeDays: Input::intOrNull(Arr::get($data, 'shelf_life_days')),
            isActive: (bool) Arr::get($data, 'is_active', true),
            specifications: array_values((array) Arr::get($data, 'specifications', [])),
            units: array_values((array) Arr::get($data, 'units', [])),
        );
    }

    public function toArray(): array
    {
        return [
            'category_id' => $this->categoryId,
            'code' => $this->code,
            'name' => $this->name,
            'base_unit_of_measure_id' => $this->baseUnitOfMeasureId,
            'tracking_type' => $this->trackingType->value,
            'description' => $this->description,
            'brand' => $this->brand,
            'model' => $this->model,
            'manufacturer_part_number' => $this->manufacturerPartNumber,
            'minimum_stock' => $this->minimumStock,
            'maximum_stock' => $this->maximumStock,
            'reorder_level' => $this->reorderLevel,
            'reorder_quantity' => $this->reorderQuantity,
            'standard_cost' => $this->standardCost,
            'valuation_method' => $this->valuationMethod->value,
            'requires_quality_inspection' => $this->requiresQualityInspection,
            'shelf_life_days' => $this->shelfLifeDays,
            'is_active' => $this->isActive,
        ];
    }
}
