<?php

namespace App\Http\Controllers\Reports;

use App\Domain\Reporting\Services\ReportExportService;
use App\Domain\Reporting\Support\ReportCatalogue;
use App\Domain\Reporting\Support\ReportRows;
use App\DTOs\Reporting\ReportCriteria;
use App\Exports\ReportExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Streams a report in the requested format. Authorisation is re-checked here so
 * a crafted URL cannot bypass the screen's permission gate.
 */
class ReportExportController
{
    public function __invoke(
        Request $request,
        ReportExportService $exports,
        string $report,
        string $format,
    ): Response|BinaryFileResponse {
        $definition = ReportCatalogue::find($report);

        if ($definition === null) {
            throw new NotFoundHttpException("Unknown report [{$report}].");
        }

        abort_unless($request->user()?->can($definition['export']), 403);

        $format = strtolower($format);

        if (! in_array($format, ['xlsx', 'csv', 'pdf'], true)) {
            throw new NotFoundHttpException("Unsupported export format [{$format}].");
        }

        $criteria = ReportCriteria::fromArray($request->query());
        $payload = $exports->payload($report, $request->user(), $criteria);
        $filename = $exports->filename($report, $format);

        if ($format === 'pdf') {
            return Pdf::loadView('reports.pdf.table', ['payload' => $payload])
                ->setPaper('a4', 'landscape')
                ->download($filename);
        }

        $export = new ReportExport(
            headings: $payload['headings'],
            rows: ReportRows::toExportArray($payload['columns'], $payload['rows']),
            formats: $exports->numberFormats($report),
            title: $payload['title'],
        );

        return Excel::download(
            $export,
            $filename,
            $format === 'csv' ? ExcelFormat::CSV : ExcelFormat::XLSX,
        );
    }
}
