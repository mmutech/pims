<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Reporting\Models\ReportSnapshot;
use App\Domain\Reporting\Support\ReportCatalogue;
use App\DTOs\Reporting\ReportCriteria;
use App\Models\User;
use App\Support\Decimal;
use Carbon\CarbonImmutable;

/**
 * Writes a daily immutable snapshot for each configured report. Snapshots keep
 * a bounded sample of rows plus the exact totals, so trend lines can be drawn
 * later without re-running expensive aggregates.
 */
class ReportSnapshotService
{
    public function __construct(private readonly ReportExportService $exports) {}

    public function capture(string $key, ?string $date = null, ?User $actor = null): ReportSnapshot
    {
        $definition = ReportCatalogue::find($key);

        if ($definition === null) {
            throw new \InvalidArgumentException("Unknown report [{$key}].");
        }

        $date = $date !== null
            ? CarbonImmutable::parse($date)->toDateString()
            : CarbonImmutable::now()->toDateString();

        $payload = $this->exports->payload(
            $key,
            null,
            new ReportCriteria,
            (int) config('reports.snapshot_row_limit', 200),
        );

        return ReportSnapshot::query()->updateOrCreate(
            ['report_key' => $key, 'snapshot_date' => $date],
            [
                'row_count' => $payload['total'],
                'total_quantity' => $this->summaryValue($payload['summary'], $definition['summary_quantity'] ?? null, Decimal::QUANTITY_SCALE),
                'total_value' => $this->summaryValue($payload['summary'], $definition['summary_value'] ?? null, Decimal::MONEY_SCALE),
                'payload' => [
                    'criteria' => (new ReportCriteria)->toArray(),
                    'summary' => $payload['summary'],
                    'rows' => $payload['rows'],
                    'truncated' => $payload['truncated'],
                    'generated_at' => $payload['generated_at'],
                ],
                'generated_by' => $actor?->id,
            ],
        );
    }

    /**
     * @return list<ReportSnapshot>
     */
    public function captureAll(?User $actor = null, ?string $date = null): array
    {
        $snapshots = [];

        foreach ((array) config('reports.snapshot_reports', []) as $key) {
            $snapshots[] = $this->capture((string) $key, $date, $actor);
        }

        return $snapshots;
    }

    private function summaryValue(array $summary, ?string $key, int $scale): ?string
    {
        if ($key === null || ! isset($summary[$key])) {
            return null;
        }

        return Decimal::normalize(str_replace(',', '', (string) $summary[$key]), $scale);
    }
}
