<?php

namespace Tests\Concerns;

use App\Domain\Distribution\Models\StockIssue;
use App\Domain\Organization\Models\Project;
use App\Models\User;

/**
 * Reporting fixtures. Distribution context already provides a warehouse, an
 * item with 100 units @ 100.00 and a requester/store officer pair; this trait
 * adds the role mix needed to prove report data-scope and permissions.
 */
trait BuildsReportingFixtures
{
    use BuildsDistributionFixtures;

    /**
     * @return array<string, mixed>
     */
    protected function reportingContext(): array
    {
        $context = $this->makeDistributionContext();

        $context['auditor'] = $this->makeDistributionUser('Auditor', $context);
        $context['procurement_manager'] = $this->makeDistributionUser('Procurement Manager', $context);
        $context['store_officer'] = $this->makeDistributionUser(
            'Store Officer',
            $context,
            $context['warehouse'],
        );
        $context['requester_viewer'] = User::factory()
            ->withRole('Department Requester')
            ->create(['department_id' => $context['department']->id]);

        return $context;
    }

    /**
     * Post a stock issue for a project so the project consumption report has
     * posted distribution rows to aggregate.
     */
    protected function postedProjectIssue(
        array $context,
        Project $project,
        string $quantity = '10.0000',
    ): StockIssue {
        $requisition = $this->submitAndApproveRequisition(
            $this->makeMaterialRequisition($context, $quantity, [
                'project_id' => $project->id,
            ]),
            $context,
        );

        return $this->reserveAndIssueRequisition($requisition, $context);
    }
}
