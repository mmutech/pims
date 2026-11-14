<?php

namespace App\Console\Commands;

use App\Domain\Workflow\Services\ApprovalDelegationService;
use Illuminate\Console\Command;

class ExpireApprovalDelegations extends Command
{
    protected $signature = 'approvals:expire-delegations';

    protected $description = 'Deactivate approval delegations whose end date has passed';

    public function handle(ApprovalDelegationService $service): int
    {
        $count = $service->deactivateExpired();

        $this->info("Deactivated {$count} delegation(s).");

        return self::SUCCESS;
    }
}
