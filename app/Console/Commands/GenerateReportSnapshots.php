<?php

namespace App\Console\Commands;

use App\Domain\Reporting\Services\ReportSnapshotService;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class GenerateReportSnapshots extends Command
{
    protected $signature = 'reports:snapshots
        {--date= : Business date to capture (YYYY-MM-DD, defaults to today)}
        {--user= : User ID recorded as the generator (defaults to the system account)}';

    protected $description = 'Capture the configured daily report snapshots';

    public function handle(ReportSnapshotService $service): int
    {
        $date = $this->option('date') !== null
            ? CarbonImmutable::parse((string) $this->option('date'))->toDateString()
            : CarbonImmutable::now()->toDateString();

        $user = null;

        if ($this->option('user') !== null) {
            $user = User::query()->find((int) $this->option('user'));

            if ($user === null) {
                $this->error('That user does not exist.');

                return self::FAILURE;
            }
        }

        $snapshots = $service->captureAll($user, $date);

        $this->info(sprintf(
            'Captured %d report snapshot(s) for %s.',
            count($snapshots),
            $date,
        ));

        foreach ($snapshots as $snapshot) {
            $this->line(sprintf(
                '  - %s (%d rows)',
                $snapshot->report_key,
                $snapshot->row_count,
            ));
        }

        return self::SUCCESS;
    }
}
