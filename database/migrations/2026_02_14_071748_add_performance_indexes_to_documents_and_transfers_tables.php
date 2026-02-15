<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->index(['current_department_id', 'status', 'updated_at'], 'documents_department_status_updated_idx');
            $table->index(['current_user_id', 'current_department_id', 'status'], 'documents_user_department_status_idx');
            $table->index(['status', 'due_at', 'updated_at'], 'documents_status_due_updated_idx');
            $table->index(['document_case_id', 'updated_at'], 'documents_case_updated_idx');
        });

        Schema::table('document_transfers', function (Blueprint $table) {
            $table->index(['document_id', 'id'], 'document_transfers_document_latest_idx');
            $table->index(['to_department_id', 'status', 'id'], 'document_transfers_to_status_latest_idx');
            $table->index(['forwarded_by_user_id', 'status', 'id'], 'document_transfers_forwarder_status_latest_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('documents_department_status_updated_idx');
            $table->dropIndex('documents_user_department_status_idx');
            $table->dropIndex('documents_status_due_updated_idx');
            $table->dropIndex('documents_case_updated_idx');
        });

        Schema::table('document_transfers', function (Blueprint $table) {
            $table->dropIndex('document_transfers_document_latest_idx');
            $table->dropIndex('document_transfers_to_status_latest_idx');
            $table->dropIndex('document_transfers_forwarder_status_latest_idx');
        });
    }
};
