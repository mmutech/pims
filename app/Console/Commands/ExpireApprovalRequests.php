<?php

namespace App\Console\Commands;

use App\Domain\Workflow\Services\ApprovalExpiryService;
use Illuminate\Console\Command;

class ExpireApprovalRequests extends Command
{
    protected $signature = 'approvals:expire';

    protected $description = 'Expire approval requests whose validity date has passed';

    public function handle(ApprovalExpiryService $service): int
    {
        $count = $service->expireRequests();

        $this->info("Expired {$count} approval request(s).");

        return self::SUCCESS;
    }
}
