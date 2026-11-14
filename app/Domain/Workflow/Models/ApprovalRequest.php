<?php

namespace App\Domain\Workflow\Models;

use App\Domain\Workflow\Contracts\Approvable;
use App\Domain\Workflow\Services\ApprovalDocumentRegistry;
use App\Enums\ApprovalStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class ApprovalRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_type',
        'document_id',
        'approval_flow_id',
        'status',
        'current_stage_sequence',
        'requested_by',
        'requested_at',
        'expires_at',
        'completed_at',
        'active_key',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApprovalStatus::class,
            'current_stage_sequence' => 'integer',
            'requested_at' => 'datetime',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(ApprovalFlow::class, 'approval_flow_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(ApprovalRequestStage::class)->orderBy('sequence');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalAction::class)->orderBy('acted_at')->orderBy('id');
    }

    public function escalations(): HasManyThrough
    {
        return $this->hasManyThrough(
            ApprovalEscalation::class,
            ApprovalRequestStage::class,
            'approval_request_id',
            'approval_request_stage_id',
        );
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(ApprovalNotification::class);
    }

    /**
     * Resolve the document using the logical document-type registry.
     */
    public function document(): ?Model
    {
        $class = app(ApprovalDocumentRegistry::class)->resolve($this->document_type);

        if ($class === null) {
            return null;
        }

        return $class::query()->find($this->document_id);
    }

    public function approvable(): ?Approvable
    {
        $document = $this->document();

        return $document instanceof Approvable ? $document : null;
    }

    public function currentStage(): ?ApprovalRequestStage
    {
        return $this->stages
            ->firstWhere('sequence', $this->current_stage_sequence);
    }

    public function isPending(): bool
    {
        return $this->status === ApprovalStatus::Pending;
    }

    public function isOverdue(): bool
    {
        $stage = $this->currentStage();

        return $stage !== null && $stage->isOverdue();
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->stages()
            ->whereHas('assignees', fn (Builder $query) => $query->where('user_id', $user->id))
            ->exists();
    }

    public function isAssignedToCurrentStage(User $user): bool
    {
        $stage = $this->currentStage();

        return $stage !== null
            && $stage->assignees()->where('user_id', $user->id)->exists();
    }

    public function isDelegatedToCurrentStage(User $user): bool
    {
        $stage = $this->currentStage();

        if ($stage === null) {
            return false;
        }

        return ApprovalDelegation::query()
            ->active()
            ->forDocumentType($this->document_type)
            ->where('delegate_id', $user->id)
            ->whereIn('delegator_id', $stage->assignees()->pluck('user_id'))
            ->exists();
    }

    public function isDelegatedTo(User $user): bool
    {
        $assigneeIds = ApprovalStageAssignee::query()
            ->whereIn('approval_request_stage_id', $this->stages()->pluck('id'))
            ->pluck('user_id');

        return ApprovalDelegation::query()
            ->active()
            ->forDocumentType($this->document_type)
            ->where('delegate_id', $user->id)
            ->whereIn('delegator_id', $assigneeIds)
            ->exists();
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ApprovalStatus::Pending->value);
    }

    public function scopeForDocument(Builder $query, Model&Approvable $document): Builder
    {
        return $query->where('document_type', $document->approvalDocumentType())
            ->where('document_id', $document->getKey());
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term): void {
            $query->where('document_type', 'like', "%{$term}%")
                ->orWhereHas('requester', fn (Builder $query) => $query->where('name', 'like', "%{$term}%"));
        });
    }
}
