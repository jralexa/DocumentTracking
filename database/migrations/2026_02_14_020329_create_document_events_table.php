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
        Schema::create('document_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_transfer_id')->nullable()->constrained('document_transfers')->nullOnDelete();
            $table->foreignId('document_custody_id')->nullable()->constrained('document_custodies')->nullOnDelete();
            $table->foreignId('document_relationship_id')->nullable()->constrained('document_relationships')->nullOnDelete();
            $table->foreignId('acted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type')->index();
            $table->string('context')->default('general')->index();
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['document_id', 'occurred_at']);
            $table->index(['event_type', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_events');
    }
};
