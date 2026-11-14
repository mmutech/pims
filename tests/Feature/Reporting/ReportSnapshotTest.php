<?php

use App\Domain\Reporting\Models\ReportSnapshot;
use App\Domain\Reporting\Services\ReportSnapshotService;
use App\Domain\Reporting\Support\ReportCatalogue;
use App\Jobs\Reporting\GenerateReportSnapshots;

it('captures a snapshot with totals and overwrites the same business date', function () {
    $context = $this->reportingContext();
    $snapshots = app(ReportSnapshotService::class);

    $snapshot = $snapshots->capture(
        ReportCatalogue::INVENTORY_VALUATION,
        '2026-11-12',
        $context['auditor'],
    );

    expect($snapshot->report_key)->toBe(ReportCatalogue::INVENTORY_VALUATION)
        ->and($snapshot->row_count)->toBe(1)
        ->and((string) $snapshot->total_quantity)->toBe('100.0000')
        ->and((string) $snapshot->total_value)->toBe('10000.00')
        ->and($snapshot->generated_by)->toBe($context['auditor']->id)
        ->and($snapshot->payload['summary']['Value'])->toBe('10,000.00')
        ->and($snapshot->payload['rows'])->toHaveCount(1)
        ->and($snapshot->payload['truncated'])->toBeFalse();

    $this->receiveStock($context, '25.0000', '100.0000');

    $snapshots->capture(ReportCatalogue::INVENTORY_VALUATION, '2026-11-12', $context['auditor']);

    expect(ReportSnapshot::query()->count())->toBe(1);

    $snapshot->refresh();

    expect((string) $snapshot->total_quantity)->toBe('125.0000')
        ->and((string) $snapshot->total_value)->toBe('12500.00');
});

it('captures every configured report through the scheduled job', function () {
    $context = $this->reportingContext();

    (new GenerateReportSnapshots('2026-11-12', $context['auditor']->id))
        ->handle(app(ReportSnapshotService::class));

    $keys = ReportSnapshot::query()->orderBy('report_key')->pluck('report_key')->all();

    expect($keys)->toBe(collect(config('reports.snapshot_reports'))->sort()->values()->all())
        ->and(ReportSnapshot::query()->where('snapshot_date', '2026-11-12')->count())->toBe(4);

    $valuation = ReportSnapshot::query()
        ->where('report_key', ReportCatalogue::INVENTORY_VALUATION)
        ->firstOrFail();

    expect((string) $valuation->total_quantity)->toBe('100.0000')
        ->and((string) $valuation->total_value)->toBe('10000.00');
});
