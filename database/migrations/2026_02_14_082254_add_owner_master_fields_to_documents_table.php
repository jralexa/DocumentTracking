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
            $table->foreignId('owner_district_id')->nullable()->after('owner_type')->constrained('districts')->nullOnDelete();
            $table->foreignId('owner_school_id')->nullable()->after('owner_district_id')->constrained('schools')->nullOnDelete();
            $table->index(['owner_type', 'owner_district_id']);
            $table->index(['owner_type', 'owner_school_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['owner_type', 'owner_district_id']);
            $table->dropIndex(['owner_type', 'owner_school_id']);
            $table->dropConstrainedForeignId('owner_school_id');
            $table->dropConstrainedForeignId('owner_district_id');
        });
    }
};
