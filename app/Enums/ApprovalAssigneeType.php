<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ApprovalAssigneeType: string
{
    use EnumHelpers;

    case Role = 'role';
    case User = 'user';
    case DepartmentManager = 'department_manager';
    case ProjectManager = 'project_manager';
    case WarehouseManager = 'warehouse_manager';
    case RequesterManager = 'requester_manager';

    public function requiresRole(): bool
    {
        return $this === self::Role;
    }

    public function requiresUser(): bool
    {
        return $this === self::User;
    }

    public function isSupported(): bool
    {
        return ! in_array($this, [self::DepartmentManager, self::RequesterManager], true);
    }
}
