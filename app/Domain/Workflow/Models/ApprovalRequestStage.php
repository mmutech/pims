<?php

namespace App\Domain\Workflow\Models;

use App\Enums\ApprovalMode;
use App\Enums\ApprovalRequestStageStatus;
use App\Enums\ApprovalStageAssigneeStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalRequestStage extends Model
{
    use HasFactory;

    protected $fillable = [
        'approval_request_id',
        'approval_stage_id',
        'sequence',
        'name',
        'approval_mode',
        'prevent_self_approval',
        'is_final',
        'status',
        'required_approvals',
        'received_approvals',
        'started_at',
        'sla_due_at',
        'completed_at',
        'blocked_reason',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'approval_mode' => ApprovalMode::class,
            'prevent_self_approval' => 'boolean',
            'is_final' => 'boolean',
            'status' => ApprovalRequestStageStatus::class,
            'required_approvals' => 'integer',
            'received_approvals' => 'integer',
            'started_at' => 'datetime',
            'sla_due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(ApprovalStage::class, 'approval_stage_id');
    }

    public function assignees(): HasMany
    {
        return $this->hasMany(ApprovalStageAssignee::class)->orderBy('id');
    }

    public function pendingAssignees(): HasMany
    {
        return $this->assignees()->where('status', ApprovalStageAssigneeStatus::Pending->value);
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function isOverdue(): bool
    {
        return $this->isOpen()
            && $this->sla_due_at !== null
            && $this->sla_due_at->isPast();
    }

    public function isSatisfied(): bool
    {
        return $this->received_approvals >= $this->required_approvals;
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ApprovalRequestStageStatus::Pending->value,
            ApprovalRequestStageStatus::InProgress->value,
            ApprovalRequestStageStatus::Blocked->value,
        ]);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now());
    }
}
