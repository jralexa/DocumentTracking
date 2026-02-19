<?php

use App\DocumentEventType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('document_cases', function (Blueprint $table): void {
            $table
                ->foreignId('opened_by_user_id')
                ->nullable()
                ->after('owner_reference')
                ->constrained('users')
                ->nullOnDelete()
                ->index();
        });

        DB::table('document_cases')
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function ($cases): void {
                $caseIds = $cases->pluck('id')->all();
                if ($caseIds === []) {
                    return;
                }

                $firstEventIdsByCase = DB::table('documents')
                    ->join('document_events', 'document_events.document_id', '=', 'documents.id')
                    ->whereIn('documents.document_case_id', $caseIds)
                    ->where('document_events.event_type', DocumentEventType::DocumentCreated->value)
                    ->whereNotNull('document_events.acted_by_user_id')
                    ->groupBy('documents.document_case_id')
                    ->selectRaw('documents.document_case_id as case_id, MIN(document_events.id) as first_event_id')
                    ->pluck('first_event_id', 'case_id');

                if ($firstEventIdsByCase->isEmpty()) {
                    return;
                }

                $actorsByEventId = DB::table('document_events')
                    ->whereIn('id', $firstEventIdsByCase->values()->all())
                    ->pluck('acted_by_user_id', 'id');

                foreach ($firstEventIdsByCase as $caseId => $eventId) {
                    $openedByUserId = $actorsByEventId->get($eventId);

                    if ($openedByUserId === null) {
                        continue;
                    }

                    DB::table('document_cases')
                        ->where('id', (int) $caseId)
                        ->update(['opened_by_user_id' => (int) $openedByUserId]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_cases', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('opened_by_user_id');
        });
    }
};
