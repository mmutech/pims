<?php

namespace App\Domain\Workflow\Models;

use App\Enums\ApprovalConditionOperator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalStageCondition extends Model
{
    use HasFactory;

    protected $fillable = [
        'approval_stage_id',
        'field',
        'operator',
        'value',
        'logical_group',
    ];

    protected function casts(): array
    {
        return [
            'operator' => ApprovalConditionOperator::class,
            'value' => 'array',
            'logical_group' => 'integer',
        ];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(ApprovalStage::class, 'approval_stage_id');
    }
}
