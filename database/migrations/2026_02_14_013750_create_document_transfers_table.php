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
        Schema::create('document_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('to_department_id')->constrained('departments');
            $table->foreignId('forwarded_by_user_id')->constrained('users');
            $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->index();
            $table->text('remarks')->nullable();
            $table->timestamp('forwarded_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('recalled_at')->nullable();
            $table->foreignId('recalled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['to_department_id', 'status', 'accepted_at']);
            $table->index(['forwarded_by_user_id', 'status']);
            $table->index(['document_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_transfers');
    }
};
