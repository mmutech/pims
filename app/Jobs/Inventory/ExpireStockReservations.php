<?php

namespace App\Jobs\Inventory;

use App\Domain\Inventory\Services\StockReservationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Releases outstanding quantities for reservations whose expiry date has passed.
 */
class ExpireStockReservations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public ?string $at = null) {}

    public function handle(StockReservationService $reservations): void
    {
        $count = $reservations->expire(
            $this->at !== null ? Carbon::parse($this->at) : null,
        );

        Log::info('Expired stock reservations.', ['count' => $count]);
    }
}
