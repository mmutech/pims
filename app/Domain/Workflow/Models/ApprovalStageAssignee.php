<?php

namespace App\Domain\Workflow\Models;

use App\Enums\ApprovalStageAssigneeStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalStageAssignee extends Model
{
    use HasFactory;

    protected $fillable = [
        'approval_request_stage_id',
        'user_id',
        'status',
        'is_delegated',
        'delegated_from_id',
        'acted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApprovalStageAssigneeStatus::class,
            'is_delegated' => 'boolean',
            'acted_at' => 'datetime',
        ];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequestStage::class, 'approval_request_stage_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function delegatedFrom(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegated_from_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ApprovalStageAssigneeStatus::Pending->value);
    }
}
