<?php

namespace App\Services;

use App\DocumentAlertType;
use App\DocumentWorkflowStatus;
use App\Models\Document;
use App\Models\DocumentAlert;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DocumentAlertService
{
    /**
     * Generate and resolve active alerts.
     *
     * @return array<string, int>
     */
    public function generateAlerts(?Carbon $referenceTime = null): array
    {
        $now = ($referenceTime ?? now())->copy();
        $created = 0;
        $resolved = 0;

        [$createdOverdue, $resolvedOverdue] = $this->syncOverdueAlerts($now);
        $created += $createdOverdue;
        $resolved += $resolvedOverdue;

        [$createdStalled, $resolvedStalled] = $this->syncStalledAlerts($now);
        $created += $createdStalled;
        $resolved += $resolvedStalled;

        return [
            'created' => $created,
            'resolved' => $resolved,
        ];
    }

    /**
     * Get dashboard alert counters and recent alert listing for a user.
     *
     * @return array<string, mixed>
     */
    public function getDashboardData(User $user): array
    {
        if (! $user->canProcessDocuments() || $user->department_id === null) {
            return [
                'counts' => [
                    'total_active' => 0,
                    'overdue' => 0,
                    'stalled' => 0,
                ],
                'recent_alerts' => collect(),
            ];
        }

        $alertsQuery = DocumentAlert::query()
            ->where('department_id', $user->department_id)
            ->where('is_active', true);

        return [
            'counts' => [
                'total_active' => (clone $alertsQuery)->count(),
                'overdue' => (clone $alertsQuery)->where('alert_type', DocumentAlertType::Overdue->value)->count(),
                'stalled' => (clone $alertsQuery)->where('alert_type', DocumentAlertType::Stalled->value)->count(),
            ],
            'recent_alerts' => (clone $alertsQuery)
                ->with('document:id,tracking_number,subject')
                ->latest('triggered_at')
                ->limit(10)
                ->get(),
        ];
    }

    /**
     * Sync overdue alerts against current document state.
     *
     * @return array{0:int,1:int}
     */
    protected function syncOverdueAlerts(Carbon $referenceTime): array
    {
        $overdueDocuments = Document::query()
            ->whereNotNull('due_at')
            ->where('due_at', '<', $referenceTime)
            ->where('status', '!=', DocumentWorkflowStatus::Finished->value)
            ->whereNotNull('current_department_id')
            ->get([
                'id',
                'current_department_id',
                'current_user_id',
                'tracking_number',
                'subject',
                'due_at',
            ]);

        return $this->syncAlertsForDocuments(
            alertType: DocumentAlertType::Overdue,
            documents: $overdueDocuments,
            referenceTime: $referenceTime,
            messageBuilder: static fn (Document $document): string => sprintf(
                'Document %s is overdue since %s.',
                $document->tracking_number,
                optional($document->due_at)->format('Y-m-d') ?? 'unknown date'
            ),
            metadataBuilder: static fn (Document $document): array => [
                'due_at' => optional($document->due_at)->toDateTimeString(),
            ]
        );
    }

    /**
     * Sync stalled alerts against current document state.
     *
     * @return array{0:int,1:int}
     */
    protected function syncStalledAlerts(Carbon $referenceTime): array
    {
        $stalledThreshold = $referenceTime->copy()->subDays(3);
        $stalledDocuments = Document::query()
            ->where('status', DocumentWorkflowStatus::OnQueue->value)
            ->where('updated_at', '<=', $stalledThreshold)
            ->whereNotNull('current_department_id')
            ->get([
                'id',
                'current_department_id',
                'current_user_id',
                'tracking_number',
                'subject',
                'updated_at',
            ]);

        return $this->syncAlertsForDocuments(
            alertType: DocumentAlertType::Stalled,
            documents: $stalledDocuments,
            referenceTime: $referenceTime,
            messageBuilder: static fn (Document $document): string => sprintf(
                'Document %s has been stalled in queue since %s.',
                $document->tracking_number,
                optional($document->updated_at)->format('Y-m-d H:i') ?? 'unknown time'
            ),
            metadataBuilder: static fn (Document $document): array => [
                'stalled_since' => optional($document->updated_at)->toDateTimeString(),
            ]
        );
    }

    /**
     * Synchronize active alerts for a specific alert type.
     *
     * @param  Collection<int, Document>  $documents
     * @param  callable(Document): string  $messageBuilder
     * @param  callable(Document): array<string, mixed>  $metadataBuilder
     * @return array{0:int,1:int}
     */
    protected function syncAlertsForDocuments(
        DocumentAlertType $alertType,
        Collection $documents,
        Carbon $referenceTime,
        callable $messageBuilder,
        callable $metadataBuilder
    ): array {
        $created = 0;
        $resolved = 0;
        $activeDocumentIds = $documents->pluck('id')->all();

        $existingActiveAlerts = DocumentAlert::query()
            ->where('alert_type', $alertType->value)
            ->where('is_active', true)
            ->get()
            ->keyBy('document_id');

        foreach ($documents as $document) {
            if ($existingActiveAlerts->has($document->id)) {
                continue;
            }

            DocumentAlert::query()->create([
                'document_id' => $document->id,
                'department_id' => $document->current_department_id,
                'user_id' => $document->current_user_id,
                'alert_type' => $alertType,
                'severity' => $alertType === DocumentAlertType::Overdue ? 'critical' : 'warning',
                'message' => $messageBuilder($document),
                'metadata' => $metadataBuilder($document),
                'is_active' => true,
                'triggered_at' => $referenceTime,
            ]);

            $created++;
        }

        $alertsToResolve = DocumentAlert::query()
            ->where('alert_type', $alertType->value)
            ->where('is_active', true)
            ->whereNotIn('document_id', $activeDocumentIds)
            ->get();

        foreach ($alertsToResolve as $alert) {
            $alert->forceFill([
                'is_active' => false,
                'resolved_at' => $referenceTime,
            ])->save();

            $resolved++;
        }

        return [$created, $resolved];
    }
}
