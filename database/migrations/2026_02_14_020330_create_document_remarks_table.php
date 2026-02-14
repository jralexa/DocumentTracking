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
        Schema::create('document_remarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_transfer_id')->nullable()->constrained('document_transfers')->nullOnDelete();
            $table->foreignId('document_item_id')->nullable()->constrained('document_items')->nullOnDelete();
            $table->foreignId('parent_remark_id')->nullable()->constrained('document_remarks')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('context')->default('general')->index();
            $table->text('remark');
            $table->boolean('is_system')->default(false)->index();
            $table->timestamp('remarked_at')->index();
            $table->timestamps();

            $table->index(['document_id', 'remarked_at']);
            $table->index(['document_transfer_id', 'remarked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_remarks');
    }
};
