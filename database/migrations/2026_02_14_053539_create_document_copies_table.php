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
        Schema::create('document_copies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_transfer_id')->nullable()->constrained('document_transfers')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('copy_type')->index();
            $table->string('storage_location')->nullable();
            $table->text('purpose')->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->boolean('is_discarded')->default(false);
            $table->timestamp('discarded_at')->nullable();
            $table->index(['document_id', 'copy_type']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_copies');
    }
};
