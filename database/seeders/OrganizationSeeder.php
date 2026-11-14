<?php

namespace Database\Seeders;

use App\Domain\Organization\Models\BusinessUnit;
use App\Domain\Organization\Models\CostCenter;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Project;
use App\Enums\ProjectStatus;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $ikeja = BusinessUnit::updateOrCreate(
            ['code' => 'BU-IKJ'],
            ['name' => 'Ikeja Business Unit', 'description' => 'Ikeja operations', 'is_active' => true],
        );

        $lekki = BusinessUnit::updateOrCreate(
            ['code' => 'BU-LKK'],
            ['name' => 'Lekki Business Unit', 'description' => 'Lekki operations', 'is_active' => true],
        );

        $garki = BusinessUnit::updateOrCreate(
            ['code' => 'BU-GRK'],
            ['name' => 'Garki Business Unit', 'description' => 'Garki operations', 'is_active' => true],
        );

        $wuse = BusinessUnit::updateOrCreate(
            ['code' => 'BU-WSE'],
            ['name' => 'Wuse Business Unit', 'description' => 'Wuse operations', 'is_active' => true],
        );

        $departments = [
            ['code' => 'DEPT-TECH', 'name' => 'Technical Services'],
            ['code' => 'DEPT-FIN', 'name' => 'Finance'],
            ['code' => 'DEPT-OPS', 'name' => 'Operations'],
            ['code' => 'DEPT-PROC', 'name' => 'Procurement'],
            ['code' => 'DEPT-MAINT', 'name' => 'Maintenance'],
        ];

        foreach ($departments as $department) {
            Department::updateOrCreate(['code' => $department['code']], [...$department, 'is_active' => true]);
        }

        $costCenters = [
            ['department_id' => Department::where('code', 'DEPT-TECH')->value('id'), 'code' => 'CC-TECH-01', 'name' => 'Technical Operations'],
            ['department_id' => Department::where('code', 'DEPT-FIN')->value('id'), 'code' => 'CC-FIN-01', 'name' => 'Finance Operations'],
            ['department_id' => Department::where('code', 'DEPT-OPS')->value('id'), 'code' => 'CC-OPS-01', 'name' => 'Lekki Operations'],
            ['department_id' => Department::where('code', 'DEPT-PROC')->value('id'), 'code' => 'CC-PROC-01', 'name' => 'Procurement Operations'],
            ['department_id' => Department::where('code', 'DEPT-MAINT')->value('id'), 'code' => 'CC-MAINT-01', 'name' => 'Maintenance Operations'],
        ];

        foreach ($costCenters as $costCenter) {
            CostCenter::updateOrCreate(['code' => $costCenter['code']], [...$costCenter, 'is_active' => true]);
        }

        $projects = [
            [
                'code' => 'PRJ-IKJ-001',
                'name' => 'Ikeja Feeder Rehabilitation',
                'department_id' => Department::where('code', 'DEPT-TECH')->value('id'),
                'cost_center_id' => CostCenter::where('code', 'CC-TECH-01')->value('id'),
                'start_date' => now()->subMonths(3)->toDateString(),
                'end_date' => now()->addMonths(9)->toDateString(),
                'status' => ProjectStatus::Active->value,
                'budget_amount' => '45000000.00',
            ],
            [
                'code' => 'PRJ-LKK-002',
                'name' => 'Lekki Substation Upgrade',
                'department_id' => Department::where('code', 'DEPT-OPS')->value('id'),
                'cost_center_id' => CostCenter::where('code', 'CC-OPS-01')->value('id'),
                'start_date' => now()->subMonth()->toDateString(),
                'end_date' => now()->addMonths(11)->toDateString(),
                'status' => ProjectStatus::Planned->value,
                'budget_amount' => '78000000.00',
            ],
            [
                'code' => 'PRJ-GRK-003',
                'name' => 'Garki Meter Rollout',
                'department_id' => Department::where('code', 'DEPT-PROC')->value('id'),
                'cost_center_id' => CostCenter::where('code', 'CC-PROC-01')->value('id'),
                'start_date' => now()->subWeeks(2)->toDateString(),
                'end_date' => now()->addMonths(6)->toDateString(),
                'status' => ProjectStatus::Active->value,
                'budget_amount' => '120000000.00',
            ],
        ];

        foreach ($projects as $project) {
            Project::updateOrCreate(['code' => $project['code']], [...$project, 'is_active' => true]);
        }
    }
}
