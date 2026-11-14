<?php

namespace App\Domain\Reporting\Support;

use App\DTOs\Reporting\ReportCriteria;
use App\Models\User;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * Applies data-scope rules consistently across report queries: warehouse scope
 * for operational reports, project-manager or project-member scope for project
 * reports.
 */
final class ReportScope
{
    public static function warehouse(Builder $query, ?User $user, string $column = 'warehouse_id'): Builder
    {
        if ($user === null || $user->hasGlobalAccess()) {
            return $query;
        }

        $ids = $user->activeWarehouseIds();

        if ($ids === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $ids);
    }

    public static function project(Builder $query, ?User $user, string $projectColumn = 'material_requisitions.project_id'): Builder
    {
        if ($user === null || $user->hasGlobalAccess()) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user, $projectColumn): void {
            $query->whereExists(function (Builder $query) use ($user, $projectColumn): void {
                $query->selectRaw('1')
                    ->from('project_members')
                    ->whereColumn('project_members.project_id', $projectColumn)
                    ->where('project_members.user_id', $user->id)
                    ->where('project_members.is_active', true);
            })->orWhereExists(function (Builder $query) use ($user, $projectColumn): void {
                $query->selectRaw('1')
                    ->from('projects')
                    ->whereColumn('projects.id', $projectColumn)
                    ->where('projects.manager_id', $user->id);
            });
        });
    }

    public static function dates(Builder $query, ReportCriteria $criteria, string $column): Builder
    {
        if ($criteria->from !== null) {
            $query->whereDate($column, '>=', $criteria->from);
        }

        if ($criteria->to !== null) {
            $query->whereDate($column, '<=', $criteria->to);
        }

        return $query;
    }

    public static function datesOnTimestamp(Builder $query, ReportCriteria $criteria, string $column): Builder
    {
        if ($criteria->from !== null) {
            $query->where($column, '>=', $criteria->fromDateTime());
        }

        if ($criteria->to !== null) {
            $query->where($column, '<=', $criteria->toDateTime());
        }

        return $query;
    }

    public static function search(Builder $query, ?string $term, array $columns): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $query) use ($columns, $like): void {
            foreach ($columns as $column) {
                $query->orWhere($column, 'like', $like);
            }
        });
    }
}
