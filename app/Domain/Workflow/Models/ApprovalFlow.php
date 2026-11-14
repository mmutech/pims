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
use Illuminate\Database\Eloquent\SoftDeletes;

class ApprovalFlow extends Model
{
    use HasFactory, RecordsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'document_type',
        'description',
        'is_active',
        'version',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function stages(): HasMany
    {
        return $this->hasMany(ApprovalStage::class)->orderBy('sequence');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term): void {
            $query->where('name', 'like', "%{$term}%")
                ->orWhere('document_type', 'like', "%{$term}%");
        });
    }

    /**
     * @return list<ApprovalMode>
     */
    public static function supportedModes(): array
    {
        return ApprovalMode::cases();
    }
}
