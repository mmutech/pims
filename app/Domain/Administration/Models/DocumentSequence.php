<?php

namespace App\Domain\Administration\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    protected $fillable = [
        'document_type',
        'prefix',
        'year',
        'next_number',
        'padding',
        'reset_policy',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'next_number' => 'integer',
            'padding' => 'integer',
        ];
    }

    public function preview(): string
    {
        return sprintf('%s/%s/%s', $this->prefix, $this->year, str_pad((string) $this->next_number, $this->padding, '0', STR_PAD_LEFT));
    }
}
