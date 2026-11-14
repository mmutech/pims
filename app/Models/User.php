<?php

namespace App\Models;

use App\Domain\Organization\Models\CostCenter;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Project;
use App\Domain\Organization\Models\ProjectMember;
use App\Domain\Warehouse\Models\UserWarehouseAccess;
use App\Domain\Warehouse\Models\Warehouse;
use App\Enums\UserStatus;
use App\Enums\WarehouseAccessLevel;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, LogsActivity, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'employee_number',
        'phone',
        'job_title',
        'department_id',
        'cost_center_id',
        'status',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'last_login_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('users')
            ->logFillable()
            ->logExcept(['password', 'remember_token'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function projectMemberships(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_members')
            ->withPivot(['role', 'joined_at', 'left_at', 'is_active'])
            ->withTimestamps();
    }

    public function warehouseAccess(): HasMany
    {
        return $this->hasMany(UserWarehouseAccess::class);
    }

    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(Warehouse::class, 'user_warehouse_access')
            ->withPivot(['access_level', 'is_primary', 'granted_by', 'granted_at', 'revoked_at'])
            ->withTimestamps();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('Super Administrator');
    }

    public function hasGlobalAccess(): bool
    {
        return $this->can(Permissions::SYSTEM_ACCESS_ALL);
    }

    /**
     * @return list<int>
     */
    public function activeWarehouseIds(): array
    {
        return $this->warehouseAccess()
            ->whereNull('revoked_at')
            ->pluck('warehouse_id')
            ->all();
    }

    public function hasWarehouseAccess(Warehouse $warehouse, WarehouseAccessLevel $minimum = WarehouseAccessLevel::View): bool
    {
        $grant = $this->warehouseAccess()
            ->where('warehouse_id', $warehouse->id)
            ->whereNull('revoked_at')
            ->first();

        if ($grant !== null && $grant->access_level->satisfies($minimum)) {
            return true;
        }

        return $minimum === WarehouseAccessLevel::View && $this->hasGlobalAccess();
    }

    public function canAccessProject(Project $project): bool
    {
        return $this->hasGlobalAccess()
            || $project->manager_id === $this->id
            || $project->hasMember($this);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }
}
