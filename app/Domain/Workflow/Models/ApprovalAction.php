<?php

namespace App\Domain\Workflow\Models;

use App\Enums\ApprovalActionType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalAction extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'approval_request_id',
        'approval_request_stage_id',
        'actor_id',
        'action',
        'comment',
        'ip_address',
        'acted_at',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'action' => ApprovalActionType::class,
            'acted_at' => 'datetime',
            'created_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequestStage::class, 'approval_request_stage_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function scopeForRequest(Builder $query, ApprovalRequest $request): Builder
    {
        return $query->where('approval_request_id', $request->id);
    }
}
