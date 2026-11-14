<?php

namespace Database\Seeders;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\ItemCategory;
use App\Domain\Catalog\Models\UnitOfMeasure;
use App\Enums\ItemTrackingType;
use App\Enums\UnitOfMeasureType;
use App\Enums\ValuationMethod;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['code' => 'PCS', 'name' => 'Pieces', 'symbol' => 'pcs', 'type' => UnitOfMeasureType::Quantity, 'decimal_places' => 0],
            ['code' => 'M', 'name' => 'Metres', 'symbol' => 'm', 'type' => UnitOfMeasureType::Length, 'decimal_places' => 2],
            ['code' => 'KG', 'name' => 'Kilograms', 'symbol' => 'kg', 'type' => UnitOfMeasureType::Weight, 'decimal_places' => 3],
            ['code' => 'L', 'name' => 'Litres', 'symbol' => 'L', 'type' => UnitOfMeasureType::Volume, 'decimal_places' => 2],
            ['code' => 'BOX', 'name' => 'Boxes', 'symbol' => 'box', 'type' => UnitOfMeasureType::Quantity, 'decimal_places' => 0],
            ['code' => 'SET', 'name' => 'Sets', 'symbol' => 'set', 'type' => UnitOfMeasureType::Quantity, 'decimal_places' => 0],
            ['code' => 'ROLL', 'name' => 'Rolls', 'symbol' => 'roll', 'type' => UnitOfMeasureType::Quantity, 'decimal_places' => 0],
        ];

        foreach ($units as $unit) {
            UnitOfMeasure::updateOrCreate(
                ['code' => $unit['code']],
                [
                    'name' => $unit['name'],
                    'symbol' => $unit['symbol'],
                    'type' => $unit['type']->value,
                    'decimal_places' => $unit['decimal_places'],
                    'is_active' => true,
                ],
            );
        }

        $electrical = ItemCategory::updateOrCreate(
            ['code' => 'CAT-ELEC'],
            [
                'name' => 'Electrical Materials',
                'description' => 'Distribution network electrical materials.',
                'track_batches' => false,
                'track_serials' => false,
                'requires_quality_inspection' => true,
                'is_active' => true,
            ],
        );

        $cables = ItemCategory::updateOrCreate(
            ['code' => 'CAT-CABLE'],
            [
                'parent_id' => $electrical->id,
                'name' => 'Cables & Conductors',
                'description' => 'LV and MV cables, conductors, and accessories.',
                'track_batches' => true,
                'track_serials' => false,
                'requires_quality_inspection' => true,
                'is_active' => true,
            ],
        );

        $transformers = ItemCategory::updateOrCreate(
            ['code' => 'CAT-XFMR'],
            [
                'parent_id' => $electrical->id,
                'name' => 'Transformers',
                'description' => 'Distribution transformers and accessories.',
                'track_batches' => true,
                'track_serials' => true,
                'requires_quality_inspection' => true,
                'is_active' => true,
            ],
        );

        $meters = ItemCategory::updateOrCreate(
            ['code' => 'CAT-METER'],
            [
                'parent_id' => $electrical->id,
                'name' => 'Metering Equipment',
                'description' => 'Energy meters and metering accessories.',
                'track_batches' => true,
                'track_serials' => true,
                'requires_quality_inspection' => true,
                'is_active' => true,
            ],
        );

        $safety = ItemCategory::updateOrCreate(
            ['code' => 'CAT-SAFETY'],
            [
                'name' => 'Safety & PPE',
                'description' => 'Personal protective equipment and safety consumables.',
                'track_batches' => false,
                'track_serials' => false,
                'requires_quality_inspection' => false,
                'is_active' => true,
            ],
        );

        $tools = ItemCategory::updateOrCreate(
            ['code' => 'CAT-TOOLS'],
            [
                'name' => 'Tools & Equipment',
                'description' => 'Hand tools, test equipment, and workshop tools.',
                'track_batches' => false,
                'track_serials' => true,
                'requires_quality_inspection' => true,
                'is_active' => true,
            ],
        );

        $pieces = UnitOfMeasure::where('code', 'PCS')->firstOrFail();
        $metres = UnitOfMeasure::where('code', 'M')->firstOrFail();
        $sets = UnitOfMeasure::where('code', 'SET')->firstOrFail();
        $rolls = UnitOfMeasure::where('code', 'ROLL')->firstOrFail();

        $items = [
            [
                'category_id' => $cables->id,
                'code' => 'ITM-ABC-50',
                'name' => 'ABC Cable 50mm²',
                'description' => 'Aerial bundled conductor, 50mm² aluminium.',
                'brand' => 'Nigerchin',
                'base_unit_of_measure_id' => $metres->id,
                'tracking_type' => ItemTrackingType::Batch->value,
                'minimum_stock' => '5000.0000',
                'maximum_stock' => '50000.0000',
                'reorder_level' => '10000.0000',
                'reorder_quantity' => '20000.0000',
                'standard_cost' => '1850.0000',
                'valuation_method' => ValuationMethod::WeightedAverage->value,
                'requires_quality_inspection' => true,
            ],
            [
                'category_id' => $transformers->id,
                'code' => 'ITM-XFMR-500',
                'name' => 'Distribution Transformer 500kVA',
                'description' => '11/0.415kV, 500kVA oil-immersed distribution transformer.',
                'brand' => 'ABB',
                'base_unit_of_measure_id' => $pieces->id,
                'tracking_type' => ItemTrackingType::BatchSerial->value,
                'minimum_stock' => '2.0000',
                'maximum_stock' => '25.0000',
                'reorder_level' => '5.0000',
                'reorder_quantity' => '10.0000',
                'standard_cost' => '18500000.0000',
                'valuation_method' => ValuationMethod::Fifo->value,
                'requires_quality_inspection' => true,
            ],
            [
                'category_id' => $meters->id,
                'code' => 'ITM-MTR-1PH',
                'name' => 'Single Phase Smart Meter',
                'description' => 'Prepaid single-phase smart energy meter.',
                'brand' => 'Momas',
                'base_unit_of_measure_id' => $pieces->id,
                'tracking_type' => ItemTrackingType::BatchSerial->value,
                'minimum_stock' => '200.0000',
                'maximum_stock' => '10000.0000',
                'reorder_level' => '1000.0000',
                'reorder_quantity' => '2500.0000',
                'standard_cost' => '42000.0000',
                'valuation_method' => ValuationMethod::Fifo->value,
                'requires_quality_inspection' => true,
            ],
            [
                'category_id' => $safety->id,
                'code' => 'ITM-PPE-HELMET',
                'name' => 'Safety Helmet',
                'description' => 'Class E electrical safety helmet.',
                'brand' => '3M',
                'base_unit_of_measure_id' => $pieces->id,
                'tracking_type' => ItemTrackingType::None->value,
                'minimum_stock' => '50.0000',
                'maximum_stock' => '1000.0000',
                'reorder_level' => '100.0000',
                'reorder_quantity' => '250.0000',
                'standard_cost' => '8500.0000',
                'valuation_method' => ValuationMethod::WeightedAverage->value,
                'requires_quality_inspection' => false,
            ],
            [
                'category_id' => $tools->id,
                'code' => 'ITM-TOOL-KIT',
                'name' => 'Lineman Tool Kit',
                'description' => 'Insulated lineman hand tool set.',
                'brand' => 'Knipex',
                'base_unit_of_measure_id' => $sets->id,
                'tracking_type' => ItemTrackingType::Serial->value,
                'minimum_stock' => '5.0000',
                'maximum_stock' => '100.0000',
                'reorder_level' => '10.0000',
                'reorder_quantity' => '20.0000',
                'standard_cost' => '285000.0000',
                'valuation_method' => ValuationMethod::WeightedAverage->value,
                'requires_quality_inspection' => true,
            ],
            [
                'category_id' => $cables->id,
                'code' => 'ITM-CABLE-LV-16',
                'name' => 'LV Cable 16mm²',
                'description' => 'Single-core PVC insulated copper cable, 16mm².',
                'brand' => 'Nexans',
                'base_unit_of_measure_id' => $rolls->id,
                'tracking_type' => ItemTrackingType::Batch->value,
                'minimum_stock' => '20.0000',
                'maximum_stock' => '500.0000',
                'reorder_level' => '50.0000',
                'reorder_quantity' => '100.0000',
                'standard_cost' => '165000.0000',
                'valuation_method' => ValuationMethod::WeightedAverage->value,
                'requires_quality_inspection' => true,
            ],
        ];

        foreach ($items as $item) {
            $model = Item::updateOrCreate(
                ['code' => $item['code']],
                [...$item, 'is_active' => true],
            );

            $model->units()->updateOrCreate(
                ['unit_of_measure_id' => $item['base_unit_of_measure_id']],
                ['conversion_factor' => '1.000000', 'is_base' => true],
            );

            if ($item['code'] === 'ITM-XFMR-500') {
                $model->specifications()->updateOrCreate(
                    ['attribute' => 'Power Rating'],
                    ['value' => '500', 'unit_of_measure_id' => null, 'sort_order' => 0],
                );
                $model->specifications()->updateOrCreate(
                    ['attribute' => 'Voltage Ratio'],
                    ['value' => '11/0.415 kV', 'unit_of_measure_id' => null, 'sort_order' => 1],
                );
            }
        }
    }
}
