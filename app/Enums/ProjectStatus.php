<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ProjectStatus: string
{
    use EnumHelpers;

    case Planned = 'planned';
    case Active = 'active';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Planned => [self::Active, self::OnHold, self::Cancelled],
            self::Active => [self::OnHold, self::Completed, self::Cancelled],
            self::OnHold => [self::Active, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }
}
