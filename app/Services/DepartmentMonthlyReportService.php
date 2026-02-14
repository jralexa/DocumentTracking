<?php

namespace App\Services;

use App\DocumentWorkflowStatus;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentTransfer;
use App\TransferStatus;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DepartmentMonthlyReportService
{
    /**
     * Build department monthly metrics report.
     *
     * @return array<string, mixed>
     */
    public function buildReport(Department $department, Carbon $month): array
    {
        $startOfMonth = $month->copy()->startOfMonth();
        $endOfMonth = $month->copy()->endOfMonth();
        $agingThreshold = $endOfMonth->copy()->subDays(5);

        $receivedTransfers = DocumentTransfer::query()
            ->where('to_department_id', $department->id)
            ->whereBetween('forwarded_at', [$startOfMonth, $endOfMonth])
            ->with(['document:id,document_type', 'acceptedBy:id,name'])
            ->get();

        $processedCount = DocumentTransfer::query()
            ->where('from_department_id', $department->id)
            ->whereBetween('forwarded_at', [$startOfMonth, $endOfMonth])
            ->whereNotIn('status', [TransferStatus::Recalled->value, TransferStatus::Cancelled->value])
            ->count();

        $pendingIncomingCount = DocumentTransfer::query()
            ->where('to_department_id', $department->id)
            ->where('status', TransferStatus::Pending->value)
            ->whereNull('accepted_at')
            ->where('forwarded_at', '<=', $endOfMonth)
            ->count();

        $onQueueCount = Document::query()
            ->where('current_department_id', $department->id)
            ->where('status', DocumentWorkflowStatus::OnQueue->value)
            ->count();

        $agingIncomingCount = DocumentTransfer::query()
            ->where('to_department_id', $department->id)
            ->where('status', TransferStatus::Pending->value)
            ->whereNull('accepted_at')
            ->where('forwarded_at', '<=', $agingThreshold)
            ->count();

        $agingOnQueueCount = Document::query()
            ->where('current_department_id', $department->id)
            ->where('status', DocumentWorkflowStatus::OnQueue->value)
            ->where('updated_at', '<=', $agingThreshold)
            ->count();

        $acceptedTransfers = DocumentTransfer::query()
            ->where('to_department_id', $department->id)
            ->whereNotNull('accepted_at')
            ->whereBetween('accepted_at', [$startOfMonth, $endOfMonth])
            ->get(['forwarded_at', 'accepted_at']);

        $averageProcessingSeconds = (float) ($acceptedTransfers
            ->map(static fn (DocumentTransfer $transfer): ?int => $transfer->accepted_at?->diffInSeconds($transfer->forwarded_at))
            ->filter(static fn (?int $seconds): bool => $seconds !== null)
            ->avg() ?? 0);

        $productivity = $receivedTransfers
            ->filter(static fn (DocumentTransfer $transfer): bool => $transfer->accepted_by_user_id !== null && $transfer->accepted_at !== null)
            ->groupBy('accepted_by_user_id')
            ->map(static function ($transfers): array {
                /** @var \Illuminate\Support\Collection<int, DocumentTransfer> $transfers */
                $firstTransfer = $transfers->first();

                return [
                    'user_id' => (int) $firstTransfer->accepted_by_user_id,
                    'user_name' => $firstTransfer->acceptedBy?->name ?? 'Unknown User',
                    'processed_count' => $transfers->count(),
                ];
            })
            ->sortByDesc('processed_count')
            ->values()
            ->all();

        $documentTypeBreakdown = $receivedTransfers
            ->groupBy(static fn (DocumentTransfer $transfer): string => $transfer->document?->document_type ?? 'unknown')
            ->map(static fn ($transfers, string $documentType): array => [
                'document_type' => $documentType,
                'count' => $transfers->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();

        return [
            'department' => [
                'id' => $department->id,
                'code' => $department->code,
                'name' => $department->name,
            ],
            'month' => $startOfMonth->format('Y-m'),
            'month_label' => $startOfMonth->format('F Y'),
            'period_start' => $startOfMonth->toDateString(),
            'period_end' => $endOfMonth->toDateString(),
            'metrics' => [
                'received_count' => $receivedTransfers->count(),
                'processed_count' => $processedCount,
                'pending_incoming_count' => $pendingIncomingCount,
                'on_queue_count' => $onQueueCount,
                'pending_total_count' => $pendingIncomingCount + $onQueueCount,
                'aging_over_five_days_count' => $agingIncomingCount + $agingOnQueueCount,
                'average_processing_hours' => round($averageProcessingSeconds / 3600, 2),
            ],
            'document_types' => $documentTypeBreakdown,
            'user_productivity' => $productivity,
            'generated_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Build a downloadable CSV response for a department monthly report.
     */
    public function downloadCsv(Department $department, Carbon $month): Response
    {
        $report = $this->buildReport($department, $month);
        $csv = $this->toCsv($report);
        $filename = sprintf(
            'department-monthly-report-%s-%s.csv',
            Str::slug($department->code ?: $department->name),
            $month->format('Y-m')
        );

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Store a generated CSV report in persistent storage.
     */
    public function storeCsvReport(Department $department, Carbon $month, string $disk = 'local'): string
    {
        $report = $this->buildReport($department, $month);
        $csv = $this->toCsv($report);
        $path = sprintf(
            'reports/monthly/%s/%s.csv',
            $month->format('Y-m'),
            Str::slug($department->code ?: $department->name)
        );

        Storage::disk($disk)->put($path, $csv);

        return $path;
    }

    /**
     * Convert report array data into CSV string content.
     *
     * @param  array<string, mixed>  $report
     */
    protected function toCsv(array $report): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            return '';
        }

        fputcsv($stream, ['Department Monthly Report']);
        fputcsv($stream, ['Department', $report['department']['name']]);
        fputcsv($stream, ['Department Code', $report['department']['code']]);
        fputcsv($stream, ['Month', $report['month_label']]);
        fputcsv($stream, []);

        fputcsv($stream, ['Metric', 'Value']);
        fputcsv($stream, ['Received', $report['metrics']['received_count']]);
        fputcsv($stream, ['Processed', $report['metrics']['processed_count']]);
        fputcsv($stream, ['Pending Incoming', $report['metrics']['pending_incoming_count']]);
        fputcsv($stream, ['On Queue', $report['metrics']['on_queue_count']]);
        fputcsv($stream, ['Pending Total', $report['metrics']['pending_total_count']]);
        fputcsv($stream, ['Aging > 5 days', $report['metrics']['aging_over_five_days_count']]);
        fputcsv($stream, ['Average Processing Hours', $report['metrics']['average_processing_hours']]);
        fputcsv($stream, []);

        fputcsv($stream, ['Document Type', 'Count']);
        foreach ($report['document_types'] as $documentType) {
            fputcsv($stream, [$documentType['document_type'], $documentType['count']]);
        }

        fputcsv($stream, []);
        fputcsv($stream, ['User', 'Processed Count']);
        foreach ($report['user_productivity'] as $productivity) {
            fputcsv($stream, [$productivity['user_name'], $productivity['processed_count']]);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv === false ? '' : $csv;
    }
}
