<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Reporting\Support\ReportCatalogue;
use App\Domain\Reporting\Support\ReportRows;
use App\DTOs\Reporting\ReportCriteria;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Builds a single immutable payload for one report. Screen, Excel/CSV, PDF and
 * snapshot generation all read this payload, which guarantees the numbers on
 * screen and in a downloaded file cannot drift apart.
 */
class ReportExportService
{
    /**
     * @return array{
     *     definition: array<string, mixed>,
     *     rows: list<array<string, mixed>>,
     *     summary: array<string, string>,
     *     paginator: LengthAwarePaginator,
     *     total: int
     * }
     */
    public function paginated(string $key, ?User $user, ReportCriteria $criteria): array
    {
        $definition = $this->definition($key);
        $service = app($definition['service']);
        $paginator = $service->{$definition['method']}($user, $criteria);
        $summary = $definition['summary'] !== null
            ? $service->{$definition['summary']}($user, $criteria)
            : [];

        return [
            'definition' => $definition,
            'rows' => ReportRows::fromModels($definition['columns'], $paginator->items()),
            'summary' => $summary,
            'paginator' => $paginator,
            'total' => $paginator->total(),
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     title: string,
     *     description: string,
     *     group: string,
     *     columns: array<int, array<string, mixed>>,
     *     headings: list<string>,
     *     rows: list<array<string, mixed>>,
     *     summary: array<string, string>,
     *     truncated: bool,
     *     total: int,
     *     generated_at: string
     * }
     */
    public function payload(string $key, ?User $user, ReportCriteria $criteria, ?int $limit = null): array
    {
        $definition = $this->definition($key);
        $limit = max(1, $limit ?? (int) config('reports.export_row_limit', 50000));
        $data = $this->paginated($key, $user, $criteria->withPerPage($limit));

        return [
            'key' => $key,
            'title' => $definition['title'],
            'description' => $definition['description'],
            'group' => $definition['group'],
            'columns' => $definition['columns'],
            'headings' => ReportRows::headings($definition['columns']),
            'rows' => $data['rows'],
            'summary' => $data['summary'],
            'truncated' => $data['total'] > count($data['rows']),
            'total' => $data['total'],
            'generated_at' => now()->toDateTimeString(),
        ];
    }

    public function filename(string $key, string $extension): string
    {
        $definition = ReportCatalogue::find($key);

        $slug = Str::slug($definition['title'] ?? $key);

        return $slug.'-'.now()->format('Ymd-His').'.'.$extension;
    }

    /**
     * @return array<int, string>
     */
    public function numberFormats(string $key): array
    {
        $definition = ReportCatalogue::find($key);

        if ($definition === null) {
            return [];
        }

        return ReportRows::numberFormats($definition['columns']);
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(string $key): array
    {
        $definition = ReportCatalogue::find($key);

        if ($definition === null) {
            throw new NotFoundHttpException("Unknown report [{$key}].");
        }

        return $definition;
    }
}
