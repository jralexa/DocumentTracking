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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('current_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('current_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tracking_number')->unique();
            $table->string('reference_number')->nullable()->index();
            $table->string('subject');
            $table->string('document_type')->index();
            $table->string('owner_type')->index();
            $table->string('owner_name');
            $table->string('status')->default('draft')->index();
            $table->string('priority')->default('normal')->index();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
