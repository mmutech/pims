<?php

namespace App\Domain\Receiving\Models;

use App\Enums\InspectionFindingSeverity;
use App\Enums\InspectionFindingType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityInspectionFinding extends Model
{
    protected $fillable = [
        'quality_inspection_item_id',
        'finding_type',
        'severity',
        'description',
        'evidence_path',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'finding_type' => InspectionFindingType::class,
            'severity' => InspectionFindingSeverity::class,
        ];
    }

    public function inspectionItem(): BelongsTo
    {
        return $this->belongsTo(QualityInspectionItem::class, 'quality_inspection_item_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
