<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum GoodsReceivedNoteStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case Posted = 'posted';
    case Reversed = 'reversed';
    case Cancelled = 'cancelled';

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function isPosted(): bool
    {
        return $this === self::Posted;
    }
}
