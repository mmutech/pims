<?php

namespace App\Domain\Workflow\Models;

use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Project;
use App\Domain\Warehouse\Models\Warehouse;
use App\Enums\ApprovalAssigneeType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

class ApprovalAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'approval_stage_id',
        'assignee_type',
        'role_id',
        'user_id',
        'department_id',
        'project_id',
        'warehouse_id',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'assignee_type' => ApprovalAssigneeType::class,
            'is_primary' => 'boolean',
        ];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(ApprovalStage::class, 'approval_stage_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
