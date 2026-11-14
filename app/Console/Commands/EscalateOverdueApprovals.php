<?php

namespace App\Console\Commands;

use App\Domain\Workflow\Services\ApprovalEscalationService;
use Illuminate\Console\Command;

class EscalateOverdueApprovals extends Command
{
    protected $signature = 'approvals:escalate';

    protected $description = 'Escalate approval stages that have breached their SLA';

    public function handle(ApprovalEscalationService $service): int
    {
        $count = $service->escalateOverdueStages();

        $this->info("Escalated {$count} approval stage(s).");

        return self::SUCCESS;
    }
}
