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
        Schema::create('document_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('alert_type')->index();
            $table->string('severity')->default('warning')->index();
            $table->text('message');
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('triggered_at')->index();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->timestamps();

            $table->index(['department_id', 'is_active']);
            $table->index(['alert_type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_alerts');
    }
};
