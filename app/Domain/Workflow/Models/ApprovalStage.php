<?php

namespace App\Domain\Workflow\Models;

use App\Enums\ApprovalMode;
use App\Models\User;
use App\Support\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Role;

class ApprovalStage extends Model
{
    use HasFactory, RecordsActivity;

    protected $fillable = [
        'approval_flow_id',
        'sequence',
        'name',
        'approval_mode',
        'required_approvals',
        'sla_hours',
        'amount_min',
        'amount_max',
        'prevent_self_approval',
        'is_final',
        'escalation_role_id',
        'escalation_user_id',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'approval_mode' => ApprovalMode::class,
            'required_approvals' => 'integer',
            'sla_hours' => 'integer',
            'amount_min' => 'decimal:2',
            'amount_max' => 'decimal:2',
            'prevent_self_approval' => 'boolean',
            'is_final' => 'boolean',
        ];
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(ApprovalFlow::class, 'approval_flow_id');
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(ApprovalStageCondition::class)->orderBy('logical_group')->orderBy('id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ApprovalAssignment::class)->orderByDesc('is_primary')->orderBy('id');
    }

    public function escalationRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'escalation_role_id');
    }

    public function escalationUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalation_user_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sequence');
    }

    public function appliesToAmount(?string $amount): bool
    {
        if ($amount === null) {
            return $this->amount_min === null && $this->amount_max === null;
        }

        if ($this->amount_min !== null && bccomp($amount, (string) $this->amount_min, 2) === -1) {
            return false;
        }

        if ($this->amount_max !== null && bccomp($amount, (string) $this->amount_max, 2) !== -1) {
            return false;
        }

        return true;
    }
}
