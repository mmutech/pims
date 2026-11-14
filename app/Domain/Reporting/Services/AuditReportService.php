<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Reporting\Support\ReportScope;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\DTOs\Reporting\ReportCriteria;
use App\Enums\ApprovalStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * Audit reporting reads the append-only activity log and approval records. Both
 * sources are immutable, so these reports are safe to use as audit evidence.
 */
class AuditReportService
{
    public function activity(?User $user, ReportCriteria $criteria): LengthAwarePaginator
    {
        $query = Activity::query()
            ->leftJoin('users', function ($join): void {
                $join->on('users.id', '=', 'activity_log.causer_id')
                    ->where('activity_log.causer_type', User::class);
            })
            ->select([
                'activity_log.id',
                'activity_log.log_name',
                'activity_log.event',
                'activity_log.description',
                'activity_log.subject_type',
                'activity_log.subject_id',
                'activity_log.causer_type',
                'activity_log.causer_id',
                'activity_log.created_at',
                'users.name as causer_name',
            ])
            ->when($criteria->logName !== null, fn (Builder $query) => $query->where('activity_log.log_name', $criteria->logName))
            ->when($criteria->subjectType !== null, fn (Builder $query) => $query->where('activity_log.subject_type', $criteria->subjectType))
            ->when($criteria->search !== null, fn (Builder $query) => $query->where(function (Builder $query) use ($criteria): void {
                $query->where('activity_log.description', 'like', '%'.$criteria->search.'%')
                    ->orWhere('users.name', 'like', '%'.$criteria->search.'%');
            }));

        ReportScope::datesOnTimestamp($query, $criteria, 'activity_log.created_at');

        return $query->orderByDesc('activity_log.created_at')
            ->orderByDesc('activity_log.id')
            ->paginate($criteria->perPage)
            ->through(function (Activity $row): Activity {
                $row->created_at = $row->created_at !== null
                    ? CarbonImmutable::parse((string) $row->created_at)->toDateTimeString()
                    : null;
                $row->causer_name = $row->causer_name
                    ?? ($row->causer_type !== null ? Str::afterLast((string) $row->causer_type, '\\').' #'.$row->causer_id : 'System');
                $row->subject_label = $row->subject_type !== null
                    ? Str::afterLast((string) $row->subject_type, '\\').' #'.$row->subject_id
                    : null;

                return $row;
            });
    }

    public function approvals(?User $user, ReportCriteria $criteria): LengthAwarePaginator
    {
        $query = ApprovalRequest::query()
            ->select([
                'approval_requests.document_type',
                'approval_requests.status',
            ])
            ->selectRaw('COUNT(approval_requests.id) as requests')
            ->selectRaw('MIN(approval_requests.requested_at) as oldest_requested_at')
            ->selectRaw('MAX(approval_requests.requested_at) as latest_requested_at')
            ->when($criteria->status !== null, fn (Builder $query) => $query->where('approval_requests.status', $criteria->status))
            ->when($criteria->search !== null, fn (Builder $query) => $query->where('approval_requests.document_type', 'like', '%'.$criteria->search.'%'))
            ->groupBy('approval_requests.document_type', 'approval_requests.status')
            ->orderBy('approval_requests.document_type')
            ->orderBy('approval_requests.status');

        ReportScope::datesOnTimestamp($query, $criteria, 'approval_requests.requested_at');

        return $query->paginate($criteria->perPage)->through(function (ApprovalRequest $row): ApprovalRequest {
            $row->requests = (int) $row->requests;
            $row->status_label = $row->status instanceof ApprovalStatus
                ? $row->status->label()
                : (ApprovalStatus::tryFrom((string) $row->status)?->label() ?? (string) $row->status);
            $row->oldest_requested_at = $row->oldest_requested_at !== null
                ? CarbonImmutable::parse((string) $row->oldest_requested_at)->toDateTimeString()
                : null;
            $row->latest_requested_at = $row->latest_requested_at !== null
                ? CarbonImmutable::parse((string) $row->latest_requested_at)->toDateTimeString()
                : null;

            return $row;
        });
    }

    /**
     * @return array<string, string>
     */
    public function activitySummary(?User $user, ReportCriteria $criteria): array
    {
        $query = Activity::query();

        if ($criteria->logName !== null) {
            $query->where('log_name', $criteria->logName);
        }

        if ($criteria->subjectType !== null) {
            $query->where('subject_type', $criteria->subjectType);
        }

        ReportScope::datesOnTimestamp($query, $criteria, 'created_at');

        $totals = $query->selectRaw('COUNT(*) as events')
            ->selectRaw('COUNT(DISTINCT log_name) as modules')
            ->selectRaw('COUNT(DISTINCT causer_id) as actors')
            ->first();

        return [
            'Events' => (string) (int) ($totals->events ?? 0),
            'Modules' => (string) (int) ($totals->modules ?? 0),
            'Actors' => (string) (int) ($totals->actors ?? 0),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function approvalsSummary(?User $user, ReportCriteria $criteria): array
    {
        $query = ApprovalRequest::query();

        if ($criteria->status !== null) {
            $query->where('status', $criteria->status);
        }

        ReportScope::datesOnTimestamp($query, $criteria, 'requested_at');

        $totals = $query->selectRaw('COUNT(*) as requests')
            ->selectRaw('COUNT(DISTINCT document_type) as document_types')
            ->selectRaw('COUNT(DISTINCT requested_by) as requesters')
            ->first();

        $pending = ApprovalRequest::query()->where('status', ApprovalStatus::Pending->value)->count();

        return [
            'Requests' => (string) (int) ($totals->requests ?? 0),
            'Document types' => (string) (int) ($totals->document_types ?? 0),
            'Requesters' => (string) (int) ($totals->requesters ?? 0),
            'Pending now' => (string) $pending,
        ];
    }
}
