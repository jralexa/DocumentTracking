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
        Schema::create('document_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('related_document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('relation_type')->index();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['source_document_id', 'related_document_id', 'relation_type'], 'document_relationship_unique');
            $table->index(['source_document_id', 'relation_type']);
            $table->index(['related_document_id', 'relation_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_relationships');
    }
};
