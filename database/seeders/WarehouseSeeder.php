<?php

namespace Database\Seeders;

use App\Domain\Organization\Models\BusinessUnit;
use App\Domain\Warehouse\Models\Warehouse;
use App\Enums\WarehouseLocationType;
use App\Enums\WarehouseType;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $ikeja = BusinessUnit::where('code', 'BU-IKJ')->firstOrFail();
        $lekki = BusinessUnit::where('code', 'BU-LKK')->firstOrFail();
        $garki = BusinessUnit::where('code', 'BU-GRK')->firstOrFail();
        $wuse = BusinessUnit::where('code', 'BU-WSE')->firstOrFail();

        $warehouses = [
            [
                'code' => 'WH-LAG-CENTRAL',
                'name' => 'Lagos Central Warehouse',
                'business_unit_id' => $ikeja->id,
                'type' => WarehouseType::Central->value,
                'city' => 'Ikeja',
                'state' => 'Lagos',
                'country' => 'Nigeria',
                'address' => '12 Obafemi Awolowo Way, Ikeja',
                'phone' => '+234 700 100 0001',
                'email' => 'lagos.central@disco.test',
            ],
            [
                'code' => 'WH-LAG-LEKKI',
                'name' => 'Lekki Regional Store',
                'business_unit_id' => $lekki->id,
                'type' => WarehouseType::Regional->value,
                'city' => 'Lekki',
                'state' => 'Lagos',
                'country' => 'Nigeria',
                'address' => 'Plot 7 Admiralty Way, Lekki Phase 1',
                'phone' => '+234 700 100 0002',
                'email' => 'lekki.store@disco.test',
            ],
            [
                'code' => 'WH-ABJ-CENTRAL',
                'name' => 'Abuja Central Warehouse',
                'business_unit_id' => $garki->id,
                'type' => WarehouseType::Central->value,
                'city' => 'Abuja',
                'state' => 'FCT',
                'country' => 'Nigeria',
                'address' => 'Plot 1102 Ahmadu Bello Way, Garki',
                'phone' => '+234 700 100 0003',
                'email' => 'abuja.central@disco.test',
            ],
            [
                'code' => 'WH-ABJ-WUSE',
                'name' => 'Wuse Project Store',
                'business_unit_id' => $wuse->id,
                'type' => WarehouseType::Project->value,
                'city' => 'Abuja',
                'state' => 'FCT',
                'country' => 'Nigeria',
                'address' => '5 Adetokunbo Ademola Crescent, Wuse II',
                'phone' => '+234 700 100 0004',
                'email' => 'wuse.store@disco.test',
            ],
            [
                'code' => 'WH-TRANSIT-01',
                'name' => 'Inter-Warehouse Transit',
                'business_unit_id' => $ikeja->id,
                'type' => WarehouseType::Transit->value,
                'city' => 'Lagos',
                'state' => 'Lagos',
                'country' => 'Nigeria',
                'address' => 'Logistics Yard, Apapa',
            ],
        ];

        foreach ($warehouses as $warehouse) {
            $model = Warehouse::updateOrCreate(
                ['code' => $warehouse['code']],
                [...$warehouse, 'is_active' => true],
            );

            $this->seedLocations($model);
        }
    }

    private function seedLocations(Warehouse $warehouse): void
    {
        $locations = [
            ['code' => 'REC', 'name' => 'Receiving Bay', 'type' => WarehouseLocationType::Receiving],
            ['code' => 'MAIN', 'name' => 'Main Storage', 'type' => WarehouseLocationType::Storage],
            ['code' => 'QUAR', 'name' => 'Quarantine Area', 'type' => WarehouseLocationType::Quarantine],
            ['code' => 'RET', 'name' => 'Returns Area', 'type' => WarehouseLocationType::Returns],
        ];

        if ($warehouse->type === WarehouseType::Transit) {
            $locations = [
                ['code' => 'TRANSIT', 'name' => 'In-Transit Staging', 'type' => WarehouseLocationType::InTransit],
            ];
        }

        foreach ($locations as $location) {
            $model = $warehouse->locations()->updateOrCreate(
                ['code' => $location['code']],
                [
                    'name' => $location['name'],
                    'type' => $location['type']->value,
                    'description' => $location['name'].' at '.$warehouse->name,
                    'is_active' => true,
                ],
            );

            if ($location['type'] === WarehouseLocationType::Storage) {
                foreach (['A1', 'A2', 'B1', 'B2'] as $index => $binCode) {
                    $model->bins()->updateOrCreate(
                        ['code' => $binCode],
                        [
                            'name' => 'Rack '.$binCode,
                            'max_capacity' => (string) (1000 + ($index * 500)),
                            'is_active' => true,
                        ],
                    );
                }
            }
        }
    }
}
