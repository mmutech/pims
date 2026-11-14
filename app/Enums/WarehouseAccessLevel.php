<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum WarehouseAccessLevel: string
{
    use EnumHelpers;

    case View = 'view';
    case Operate = 'operate';
    case Approve = 'approve';
    case Manage = 'manage';

    public function rank(): int
    {
        return match ($this) {
            self::View => 1,
            self::Operate => 2,
            self::Approve => 3,
            self::Manage => 4,
        };
    }

    public function satisfies(self $required): bool
    {
        return $this->rank() >= $required->rank();
    }
}
