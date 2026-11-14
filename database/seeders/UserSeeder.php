<?php

namespace Database\Seeders;

use App\Domain\Organization\Models\CostCenter;
use App\Domain\Organization\Models\Department;
use App\Domain\Warehouse\Models\Warehouse;
use App\Enums\UserStatus;
use App\Enums\WarehouseAccessLevel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $tech = Department::where('code', 'DEPT-TECH')->first();
        $finance = Department::where('code', 'DEPT-FIN')->first();
        $procurement = Department::where('code', 'DEPT-PROC')->first();
        $techCostCenter = CostCenter::where('code', 'CC-TECH-01')->first();
        $lagosCentral = Warehouse::where('code', 'WH-LAG-CENTRAL')->first();
        $abujaCentral = Warehouse::where('code', 'WH-ABJ-CENTRAL')->first();

        $users = [
            [
                'email' => 'superadmin@disco.test',
                'name' => 'Super Administrator',
                'employee_number' => 'EMP-0001',
                'job_title' => 'System Owner',
                'role' => 'Super Administrator',
                'department_id' => null,
                'cost_center_id' => null,
            ],
            [
                'email' => 'sysadmin@disco.test',
                'name' => 'System Administrator',
                'employee_number' => 'EMP-0002',
                'job_title' => 'ICT Systems Administrator',
                'role' => 'System Administrator',
                'department_id' => null,
                'cost_center_id' => null,
            ],
            [
                'email' => 'executive@disco.test',
                'name' => 'Chief Operating Officer',
                'employee_number' => 'EMP-0003',
                'job_title' => 'Chief Operating Officer',
                'role' => 'Executive',
                'department_id' => null,
                'cost_center_id' => null,
            ],
            [
                'email' => 'procurement.manager@disco.test',
                'name' => 'Procurement Manager',
                'employee_number' => 'EMP-0004',
                'job_title' => 'Head of Procurement',
                'role' => 'Procurement Manager',
                'department_id' => $procurement?->id,
                'cost_center_id' => null,
                'warehouse' => $abujaCentral,
                'access_level' => WarehouseAccessLevel::View,
            ],
            [
                'email' => 'procurement.officer@disco.test',
                'name' => 'Procurement Officer',
                'employee_number' => 'EMP-0005',
                'job_title' => 'Procurement Officer',
                'role' => 'Procurement Officer',
                'department_id' => $procurement?->id,
                'cost_center_id' => null,
                'warehouse' => $abujaCentral,
                'access_level' => WarehouseAccessLevel::View,
            ],
            [
                'email' => 'warehouse.manager@disco.test',
                'name' => 'Lagos Warehouse Manager',
                'employee_number' => 'EMP-0006',
                'job_title' => 'Warehouse Manager',
                'role' => 'Warehouse Manager',
                'department_id' => $tech?->id,
                'cost_center_id' => $techCostCenter?->id,
                'warehouse' => $lagosCentral,
                'access_level' => WarehouseAccessLevel::Manage,
            ],
            [
                'email' => 'store.officer@disco.test',
                'name' => 'Lagos Store Officer',
                'employee_number' => 'EMP-0007',
                'job_title' => 'Store Officer',
                'role' => 'Store Officer',
                'department_id' => $tech?->id,
                'cost_center_id' => $techCostCenter?->id,
                'warehouse' => $lagosCentral,
                'access_level' => WarehouseAccessLevel::Operate,
            ],
            [
                'email' => 'project.manager@disco.test',
                'name' => 'Ikeja Project Manager',
                'employee_number' => 'EMP-0008',
                'job_title' => 'Project Manager',
                'role' => 'Project Manager',
                'department_id' => $tech?->id,
                'cost_center_id' => $techCostCenter?->id,
            ],
            [
                'email' => 'requester@disco.test',
                'name' => 'Technical Requester',
                'employee_number' => 'EMP-0009',
                'job_title' => 'Technical Officer',
                'role' => 'Department Requester',
                'department_id' => $tech?->id,
                'cost_center_id' => $techCostCenter?->id,
            ],
            [
                'email' => 'auditor@disco.test',
                'name' => 'Internal Auditor',
                'employee_number' => 'EMP-0010',
                'job_title' => 'Internal Auditor',
                'role' => 'Auditor',
                'department_id' => $finance?->id,
                'cost_center_id' => null,
            ],
            [
                'email' => 'abuja.manager@disco.test',
                'name' => 'Abuja Warehouse Manager',
                'employee_number' => 'EMP-0011',
                'job_title' => 'Warehouse Manager',
                'role' => 'Warehouse Manager',
                'department_id' => null,
                'cost_center_id' => null,
                'warehouse' => $abujaCentral,
                'access_level' => WarehouseAccessLevel::Manage,
            ],
            [
                'email' => 'quality.inspector@disco.test',
                'name' => 'Quality Inspector',
                'employee_number' => 'EMP-0012',
                'job_title' => 'Quality Assurance Inspector',
                'role' => 'Quality Inspector',
                'department_id' => $tech?->id,
                'cost_center_id' => $techCostCenter?->id,
                'warehouse' => $lagosCentral,
                'access_level' => WarehouseAccessLevel::Operate,
            ],
        ];

        foreach ($users as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'employee_number' => $data['employee_number'],
                    'job_title' => $data['job_title'],
                    'phone' => '+234 800 '.str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT),
                    'department_id' => $data['department_id'],
                    'cost_center_id' => $data['cost_center_id'],
                    'status' => UserStatus::Active,
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                ],
            );

            $user->syncRoles([$data['role']]);

            if (isset($data['warehouse']) && $data['warehouse'] !== null) {
                $user->warehouseAccess()->updateOrCreate(
                    ['warehouse_id' => $data['warehouse']->id],
                    [
                        'access_level' => $data['access_level'] ?? WarehouseAccessLevel::Operate,
                        'is_primary' => true,
                        'granted_at' => now(),
                        'revoked_at' => null,
                    ],
                );
            }
        }
    }
}
