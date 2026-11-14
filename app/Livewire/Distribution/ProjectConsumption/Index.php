<?php

namespace App\Livewire\Distribution\ProjectConsumption;

use App\Domain\Distribution\Services\StockIssueService;
use App\Livewire\Concerns\UsesDistributionOptions;
use App\Support\Permissions;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Project Consumption')]
class Index extends Component
{
    use UsesDistributionOptions;

    public string $projectId = '';

    public string $from = '';

    public string $to = '';

    /** @var array{quantity: string, value: string, lines: list<array<string, mixed>>}|null */
    public ?array $summary = null;

    public function mount(): void
    {
        abort_unless(
            auth()->user()->can(Permissions::DISTRIBUTION_PROJECT_CONSUMPTION_VIEW),
            403,
        );

        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->toDateString();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['projectId', 'from', 'to'], true)) {
            $this->summary = null;
        }
    }

    public function applyFilters(StockIssueService $service): void
    {
        $this->validate([
            'projectId' => ['required', 'integer', 'exists:projects,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $this->summary = $service->projectConsumption(
            (int) $this->projectId,
            $this->from !== '' ? $this->from : null,
            $this->to !== '' ? $this->to : null,
        );
    }

    public function render()
    {
        return view('livewire.distribution.project-consumption.index', [
            'projects' => $this->scopedProjects(),
        ]);
    }
}
