<?php

namespace App\Domain\Procurement\Models;

use App\Domain\Supplier\Models\Supplier;
use App\Enums\RfqSupplierStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfqSupplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_for_quotation_id',
        'supplier_id',
        'invited_at',
        'responded_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => RfqSupplierStatus::class,
            'invited_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function requestForQuotation(): BelongsTo
    {
        return $this->belongsTo(RequestForQuotation::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
