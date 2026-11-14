<?php

namespace App\Domain\Workflow\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalEscalation extends Model
{
    use HasFactory;

    protected $fillable = [
        'approval_request_stage_id',
        'escalated_to',
        'reason',
        'escalated_at',
        'resolved_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'escalated_at' => 'datetime',
            'resolved_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function requestStage(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequestStage::class, 'approval_request_stage_id');
    }

    public function escalatedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_to');
    }
}
