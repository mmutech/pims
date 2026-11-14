<?php

namespace App\Domain\Distribution\Models;

use App\Enums\ConditionDisposition;
use App\Enums\MaterialCondition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialReturnAssessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_return_item_id',
        'assessor_id',
        'assessed_at',
        'condition',
        'accepted_quantity',
        'rejected_quantity',
        'disposition',
        'findings',
        'evidence_path',
    ];

    protected function casts(): array
    {
        return [
            'assessed_at' => 'datetime',
            'condition' => MaterialCondition::class,
            'accepted_quantity' => 'decimal:4',
            'rejected_quantity' => 'decimal:4',
            'disposition' => ConditionDisposition::class,
        ];
    }

    public function returnItem(): BelongsTo
    {
        return $this->belongsTo(MaterialReturnItem::class, 'material_return_item_id');
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessor_id');
    }
}
