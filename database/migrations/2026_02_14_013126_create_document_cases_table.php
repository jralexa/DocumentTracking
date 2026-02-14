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
        Schema::create('document_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_number')->unique();
            $table->string('title');
            $table->string('owner_type');
            $table->string('owner_name');
            $table->string('owner_reference')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('open')->index();
            $table->string('priority')->default('normal')->index();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_cases');
    }
};
