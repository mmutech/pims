<?php

namespace App\Jobs\Reporting;

use App\Domain\Reporting\Services\ReportSnapshotService;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Captures the configured daily report snapshots. The job is idempotent: re-runs
 * for the same date overwrite the snapshot instead of duplicating it.
 */
class GenerateReportSnapshots implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public ?string $date = null,
        public ?int $generatedBy = null,
    ) {}

    public function handle(ReportSnapshotService $snapshots): void
    {
        $actor = $this->generatedBy !== null
            ? User::query()->find($this->generatedBy)
            : null;

        $date = $this->date ?? now()->toDateString();
        $captured = $snapshots->captureAll($actor, $date);

        Log::info('Generated report snapshots.', [
            'date' => $date,
            'reports' => array_map(fn ($snapshot): string => $snapshot->report_key, $captured),
        ]);
    }
}
