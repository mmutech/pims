<?php

namespace Database\Seeders;

use App\Domain\Workflow\Models\ApprovalFlow;
use App\Enums\ApprovalAssigneeType;
use App\Enums\ApprovalMode;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Demo approval routes for future procurement documents.
 *
 * Flows are only created when missing so re-seeding never destroys configuration
 * changed through the UI. Document types are inert until a Phase 3+ model
 * implements App\Domain\Workflow\Contracts\Approvable and is registered in
 * config/approvals.php.
 */
class ApprovalFlowSeeder extends Seeder
{
    public function run(): void
    {
        $createdBy = User::query()->where('email', 'superadmin@disco.test')->value('id');

        $flows = [
            [
                'name' => 'Purchase requisition approval',
                'document_type' => 'purchase_requisition',
                'description' => 'Procurement review, manager approval, and executive sign-off above ₦500,000.',
                'is_active' => true,
                'stages' => [
                    [
                        'name' => 'Procurement review',
                        'approval_mode' => ApprovalMode::AnyOne->value,
                        'sla_hours' => 24,
                        'assignments' => [[
                            'assignee_type' => ApprovalAssigneeType::Role->value,
                            'role' => 'Procurement Officer',
                        ]],
                    ],
                    [
                        'name' => 'Procurement manager approval',
                        'approval_mode' => ApprovalMode::AnyOne->value,
                        'sla_hours' => 48,
                        'assignments' => [[
                            'assignee_type' => ApprovalAssigneeType::Role->value,
                            'role' => 'Procurement Manager',
                        ]],
                    ],
                    [
                        'name' => 'Executive approval',
                        'approval_mode' => ApprovalMode::AnyOne->value,
                        'sla_hours' => 72,
                        'amount_min' => '500000.00',
                        'is_final' => true,
                        'escalation_role' => 'Executive',
                        'assignments' => [[
                            'assignee_type' => ApprovalAssigneeType::Role->value,
                            'role' => 'Executive',
                        ]],
                    ],
                ],
            ],
            [
                'name' => 'Purchase order approval',
                'document_type' => 'purchase_order',
                'description' => 'Procurement manager approval with executive sign-off for orders of ₦1,000,000 and above.',
                'is_active' => true,
                'stages' => [
                    [
                        'name' => 'Procurement manager approval',
                        'approval_mode' => ApprovalMode::AnyOne->value,
                        'sla_hours' => 24,
                        'assignments' => [[
                            'assignee_type' => ApprovalAssigneeType::Role->value,
                            'role' => 'Procurement Manager',
                        ]],
                    ],
                    [
                        'name' => 'Executive approval',
                        'approval_mode' => ApprovalMode::AnyOne->value,
                        'sla_hours' => 48,
                        'amount_min' => '1000000.00',
                        'is_final' => true,
                        'escalation_role' => 'Executive',
                        'assignments' => [[
                            'assignee_type' => ApprovalAssigneeType::Role->value,
                            'role' => 'Executive',
                        ]],
                    ],
                ],
            ],
            [
                'name' => 'Stock adjustment approval',
                'document_type' => 'stock_adjustment',
                'description' => 'Warehouse manager approval for inventory write-offs and corrections.',
                'is_active' => true,
                'stages' => [
                    [
                        'name' => 'Warehouse manager approval',
                        'approval_mode' => ApprovalMode::AnyOne->value,
                        'sla_hours' => 48,
                        'is_final' => true,
                        'assignments' => [[
                            'assignee_type' => ApprovalAssigneeType::WarehouseManager->value,
                        ]],
                    ],
                ],
            ],
            [
                'name' => 'Quotation evaluation approval',
                'document_type' => 'quotation_evaluation',
                'description' => 'Procurement manager approval of the recommended supplier before award.',
                'is_active' => true,
                'stages' => [
                    [
                        'name' => 'Procurement manager approval',
                        'approval_mode' => ApprovalMode::AnyOne->value,
                        'sla_hours' => 48,
                        'is_final' => true,
                        'assignments' => [[
                            'assignee_type' => ApprovalAssigneeType::Role->value,
                            'role' => 'Procurement Manager',
                        ]],
                    ],
                ],
            ],
            [
                'name' => 'Purchase order amendment approval',
                'document_type' => 'purchase_order_amendment',
                'description' => 'Procurement manager approval for changes to an approved purchase order.',
                'is_active' => true,
                'stages' => [
                    [
                        'name' => 'Procurement manager approval',
                        'approval_mode' => ApprovalMode::AnyOne->value,
                        'sla_hours' => 48,
                        'is_final' => true,
                        'assignments' => [[
                            'assignee_type' => ApprovalAssigneeType::Role->value,
                            'role' => 'Procurement Manager',
                        ]],
                    ],
                ],
            ],
            [
                'name' => 'Material requisition approval',
                'document_type' => 'material_requisition',
                'description' => 'Destination warehouse manager approval of internal material requests.',
                'is_active' => true,
                'stages' => [
                    [
                        'name' => 'Warehouse manager approval',
                        'approval_mode' => ApprovalMode::AnyOne->value,
                        'sla_hours' => 24,
                        'is_final' => true,
                        'assignments' => [[
                            'assignee_type' => ApprovalAssigneeType::WarehouseManager->value,
                        ]],
                    ],
                ],
            ],
            [
                'name' => 'Stock transfer approval',
                'document_type' => 'stock_transfer',
                'description' => 'Source warehouse manager approval before stock moves between warehouses.',
                'is_active' => true,
                'stages' => [
                    [
                        'name' => 'Source warehouse manager approval',
                        'approval_mode' => ApprovalMode::AnyOne->value,
                        'sla_hours' => 24,
                        'is_final' => true,
                        'assignments' => [[
                            'assignee_type' => ApprovalAssigneeType::WarehouseManager->value,
                        ]],
                    ],
                ],
            ],
            [
                'name' => 'Supplier registration approval',
                'document_type' => 'supplier_registration',
                'description' => 'Procurement manager vetting with executive endorsement.',
                'is_active' => true,
                'stages' => [
                    [
                        'name' => 'Procurement manager vetting',
                        'approval_mode' => ApprovalMode::AnyOne->value,
                        'sla_hours' => 48,
                        'assignments' => [[
                            'assignee_type' => ApprovalAssigneeType::Role->value,
                            'role' => 'Procurement Manager',
                        ]],
                    ],
                    [
                        'name' => 'Executive endorsement',
                        'approval_mode' => ApprovalMode::AnyOne->value,
                        'sla_hours' => 72,
                        'is_final' => true,
                        'assignments' => [[
                            'assignee_type' => ApprovalAssigneeType::Role->value,
                            'role' => 'Executive',
                        ]],
                    ],
                ],
            ],
        ];

        foreach ($flows as $definition) {
            $stages = $definition['stages'];
            unset($definition['stages']);

            $flow = ApprovalFlow::query()->firstOrCreate(
                ['document_type' => $definition['document_type']],
                [...$definition, 'created_by' => $createdBy],
            );

            if (! $flow->wasRecentlyCreated) {
                continue;
            }

            foreach (array_values($stages) as $index => $stageDefinition) {
                $assignments = $stageDefinition['assignments'];
                $escalationRole = $stageDefinition['escalation_role'] ?? null;
                unset($stageDefinition['assignments'], $stageDefinition['escalation_role']);

                $stage = $flow->stages()->create([
                    ...$stageDefinition,
                    'sequence' => $index + 1,
                    'prevent_self_approval' => true,
                    'escalation_role_id' => $escalationRole !== null
                        ? Role::findByName($escalationRole)->id
                        : null,
                ]);

                /** @var array<string, mixed> $assignment */
                foreach ($assignments as $assignment) {
                    $role = $assignment['role'] ?? null;
                    unset($assignment['role']);

                    $stage->assignments()->create([
                        ...$assignment,
                        'role_id' => $role !== null ? Role::findByName($role)->id : null,
                        'is_primary' => true,
                    ]);
                }
            }
        }
    }
}
