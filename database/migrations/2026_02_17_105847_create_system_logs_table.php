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
        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->string('level', 16)->default('info');
            $table->string('category', 64);
            $table->string('action', 128);
            $table->text('message');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('http_method', 10)->nullable();
            $table->string('route_name', 150)->nullable();
            $table->text('request_path')->nullable();
            $table->string('entity_type', 120)->nullable();
            $table->string('entity_id', 64)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['occurred_at']);
            $table->index(['category']);
            $table->index(['action']);
            $table->index(['level']);
            $table->index(['user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_logs');
    }
};
