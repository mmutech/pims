<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            OrganizationSeeder::class,
            WarehouseSeeder::class,
            CatalogSeeder::class,
            SupplierSeeder::class,
            UserSeeder::class,
            SettingsSeeder::class,
            DocumentSequenceSeeder::class,
            ApprovalFlowSeeder::class,
        ]);
    }
}
