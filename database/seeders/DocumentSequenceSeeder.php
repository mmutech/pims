<?php

namespace Database\Seeders;

use App\Domain\Administration\Models\DocumentSequence;
use App\Enums\DocumentType;
use Illuminate\Database\Seeder;

class DocumentSequenceSeeder extends Seeder
{
    public function run(): void
    {
        $year = (int) now()->format('Y');

        foreach (DocumentType::cases() as $type) {
            DocumentSequence::updateOrCreate(
                ['document_type' => $type->value, 'year' => $year],
                [
                    'prefix' => $type->prefix(),
                    'next_number' => 1,
                    'padding' => 5,
                    'reset_policy' => 'yearly',
                ],
            );
        }
    }
}
