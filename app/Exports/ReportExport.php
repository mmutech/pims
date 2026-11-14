<?php

namespace App\Exports;

use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Generic worksheet for any report catalogue entry. Rows are already
 * normalised by the reporting domain, so the export contains the same values
 * the user saw on screen.
 */
class ReportExport implements FromArray, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithTitle
{
    /**
     * @param  list<string>  $headings
     * @param  list<list<mixed>>  $rows
     * @param  array<int, string>  $formats
     */
    public function __construct(
        private readonly array $headings,
        private readonly array $rows,
        private readonly array $formats,
        private readonly string $title,
    ) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function columnFormats(): array
    {
        return $this->formats;
    }

    public function title(): string
    {
        return Str::limit($this->title, 31, '');
    }
}
