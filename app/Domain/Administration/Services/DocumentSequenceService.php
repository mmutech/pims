<?php

namespace App\Domain\Administration\Services;

use App\Domain\Administration\Models\DocumentSequence;
use App\Enums\DocumentType;
use DomainException;
use Illuminate\Support\Facades\DB;

class DocumentSequenceService
{
    public function create(DocumentType $type, string $prefix, int $year, int $nextNumber = 1, int $padding = 5, string $resetPolicy = 'yearly'): DocumentSequence
    {
        $this->validate($padding, $nextNumber, $resetPolicy);

        return DocumentSequence::create([
            'document_type' => $type->value,
            'prefix' => strtoupper($prefix),
            'year' => $year,
            'next_number' => $nextNumber,
            'padding' => $padding,
            'reset_policy' => $resetPolicy,
        ]);
    }

    public function update(DocumentSequence $sequence, int $nextNumber, int $padding, string $resetPolicy): DocumentSequence
    {
        $this->validate($padding, $nextNumber, $resetPolicy);

        $sequence->update([
            'next_number' => $nextNumber,
            'padding' => $padding,
            'reset_policy' => $resetPolicy,
        ]);

        return $sequence->refresh();
    }

    /**
     * Allocate the next document number under a row lock.
     */
    public function nextNumber(DocumentType $type, ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');

        return DB::transaction(function () use ($type, $year): string {
            $sequence = DocumentSequence::where('document_type', $type->value)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                $sequence = DocumentSequence::create([
                    'document_type' => $type->value,
                    'prefix' => $type->prefix(),
                    'year' => $year,
                    'next_number' => 1,
                    'padding' => 5,
                    'reset_policy' => 'yearly',
                ]);
            }

            $number = sprintf(
                '%s/%s/%s',
                $sequence->prefix,
                $sequence->year,
                str_pad((string) $sequence->next_number, $sequence->padding, '0', STR_PAD_LEFT),
            );

            $sequence->increment('next_number');

            return $number;
        });
    }

    private function validate(int $padding, int $nextNumber, string $resetPolicy): void
    {
        if ($padding < 1 || $padding > 10) {
            throw new DomainException('Padding must be between 1 and 10.');
        }

        if ($nextNumber < 1) {
            throw new DomainException('The next number must be at least 1.');
        }

        if (! in_array($resetPolicy, ['yearly', 'never'], true)) {
            throw new DomainException('Reset policy must be either yearly or never.');
        }
    }
}
