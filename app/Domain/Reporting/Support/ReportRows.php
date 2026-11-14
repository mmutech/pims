<?php

namespace App\Domain\Reporting\Support;

use App\Support\Decimal;
use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Converts report models into plain scalar rows. The same normalised rows feed
 * the HTML table, Excel/CSV export, PDF export and snapshot payloads, so every
 * consumer sees identical values.
 */
final class ReportRows
{
    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @param  iterable<int, mixed>  $models
     * @return list<array<string, mixed>>
     */
    public static function fromModels(array $columns, iterable $models): array
    {
        $rows = [];

        foreach ($models as $model) {
            $rows[] = self::fromModel($columns, $model);
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<string, mixed>
     */
    public static function fromModel(array $columns, mixed $model): array
    {
        $row = [];

        foreach ($columns as $column) {
            $row[$column['key']] = self::value($column, data_get($model, $column['key']));
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $column
     */
    public static function value(array $column, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($column['type'] ?? 'text') {
            'money' => Decimal::normalize((string) $value, Decimal::MONEY_SCALE),
            'quantity' => Decimal::normalize((string) $value, Decimal::QUANTITY_SCALE),
            'cost' => Decimal::normalize((string) $value, Decimal::COST_SCALE),
            'integer' => (int) $value,
            'date' => self::date($value)->toDateString(),
            'datetime' => self::date($value)->toDateTimeString(),
            default => $value instanceof \BackedEnum ? (string) $value->value : (string) $value,
        };
    }

    private static function date(mixed $value): CarbonImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        return CarbonImmutable::parse((string) $value);
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @return list<string>
     */
    public static function headings(array $columns): array
    {
        return array_map(fn (array $column): string => (string) $column['label'], $columns);
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<int, string>
     */
    public static function numberFormats(array $columns): array
    {
        $formats = [];

        foreach ($columns as $index => $column) {
            $format = match ($column['type'] ?? 'text') {
                'money' => '#,##0.00',
                'quantity' => '#,##0.0000',
                'cost' => '#,##0.000000',
                'integer' => '#,##0',
                'date' => 'yyyy-mm-dd',
                'datetime' => 'yyyy-mm-dd hh:mm',
                default => null,
            };

            if ($format !== null) {
                $formats[Coordinate::stringFromColumnIndex($index + 1)] = $format;
            }
        }

        return $formats;
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @param  list<array<string, mixed>>  $rows
     * @return list<list<mixed>>
     */
    public static function toExportArray(array $columns, array $rows): array
    {
        $exported = [];

        foreach ($rows as $row) {
            $line = [];

            foreach ($columns as $column) {
                $value = $row[$column['key']] ?? null;

                $line[] = in_array($column['type'] ?? 'text', ['money', 'quantity', 'cost'], true)
                    ? $value
                    : (is_scalar($value) || $value === null ? $value : (string) $value);
            }

            $exported[] = $line;
        }

        return $exported;
    }
}
