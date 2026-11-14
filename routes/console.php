<?php

use App\Console\Commands\EscalateOverdueApprovals;
use App\Console\Commands\ExpireApprovalDelegations;
use App\Console\Commands\ExpireApprovalRequests;
use App\Jobs\Inventory\ExpireStockReservations;
use App\Jobs\Inventory\GenerateInventoryValuationSnapshots;
use App\Jobs\Inventory\ReconcileInventoryBalances;
use App\Jobs\Reporting\GenerateReportSnapshots;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(EscalateOverdueApprovals::class)->hourly();
Schedule::command(ExpireApprovalRequests::class)->everyThirtyMinutes();
Schedule::command(ExpireApprovalDelegations::class)->daily();

Schedule::job(new ExpireStockReservations)->hourly()->withoutOverlapping();
Schedule::job(new GenerateInventoryValuationSnapshots)->dailyAt('23:55')->withoutOverlapping();
Schedule::job(new ReconcileInventoryBalances)->dailyAt('00:15')->withoutOverlapping();
Schedule::job(new GenerateReportSnapshots)->dailyAt('23:45')->withoutOverlapping();
