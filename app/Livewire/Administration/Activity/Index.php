<?php

namespace App\Livewire\Administration\Activity;

use App\Livewire\Concerns\HandlesDomainActions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

#[Layout('layouts.app')]
#[Title('Activity Log')]
class Index extends Component
{
    use HandlesDomainActions, WithPagination;

    public string $search = '';

    public string $logName = '';

    public string $subjectType = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Activity::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedLogName(): void
    {
        $this->resetPage();
    }

    public function updatedSubjectType(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'logName', 'subjectType', 'dateFrom', 'dateTo');
        $this->resetPage();
    }

    public function render()
    {
        $activities = Activity::query()
            ->with(['causer', 'subject'])
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.$this->search.'%';

                $query->where(function (Builder $query) use ($term): void {
                    $query->where('description', 'like', $term)
                        ->orWhere('subject_type', 'like', $term)
                        ->orWhere('event', 'like', $term);
                });
            })
            ->when($this->logName !== '', fn (Builder $query) => $query->where('log_name', $this->logName))
            ->when($this->subjectType !== '', fn (Builder $query) => $query->where('subject_type', $this->subjectType))
            ->when($this->dateFrom !== '', fn (Builder $query) => $query->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $query) => $query->whereDate('created_at', '<=', $this->dateTo))
            ->latest()
            ->paginate(25);

        return view('livewire.administration.activity.index', [
            'activities' => $activities,
            'logNames' => Activity::query()
                ->select('log_name')
                ->whereNotNull('log_name')
                ->distinct()
                ->orderBy('log_name')
                ->pluck('log_name'),
            'subjectTypes' => Activity::query()
                ->select('subject_type')
                ->whereNotNull('subject_type')
                ->distinct()
                ->orderBy('subject_type')
                ->pluck('subject_type'),
            'subjectLabel' => fn (?string $type): string => $type === null
                ? '—'
                : Str::headline(Str::afterLast($type, '\\')),
        ]);
    }
}
