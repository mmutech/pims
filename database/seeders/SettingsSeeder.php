<?php

namespace Database\Seeders;

use App\Domain\Administration\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'group' => 'general',
                'key' => 'company.name',
                'value' => 'Demo Electricity Distribution Company',
                'type' => 'string',
                'description' => 'Legal company name used on documents and reports.',
            ],
            [
                'group' => 'general',
                'key' => 'company.code',
                'value' => 'DISCO',
                'type' => 'string',
                'description' => 'Short company code used in generated document numbers.',
            ],
            [
                'group' => 'inventory',
                'key' => 'inventory.allow_negative_stock',
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Negative stock is never permitted in Phase 1.',
            ],
            [
                'group' => 'inventory',
                'key' => 'inventory.default_valuation_method',
                'value' => 'weighted_average',
                'type' => 'string',
                'description' => 'Default valuation method for newly created items.',
            ],
            [
                'group' => 'inventory',
                'key' => 'inventory.low_stock_notification',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Notify warehouse managers when stock falls below reorder level.',
            ],
            [
                'group' => 'security',
                'key' => 'security.prevent_self_approval',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Prevent a user from approving a record they created.',
            ],
            [
                'group' => 'security',
                'key' => 'security.session_timeout_minutes',
                'value' => '30',
                'type' => 'integer',
                'description' => 'Idle session timeout in minutes.',
            ],
            [
                'group' => 'documents',
                'key' => 'documents.reset_yearly',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Document sequences reset each calendar year.',
            ],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                [...$setting, 'is_encrypted' => false],
            );
        }
    }
}
