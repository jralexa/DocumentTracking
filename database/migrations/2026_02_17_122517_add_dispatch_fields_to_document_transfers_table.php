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
        Schema::table('document_transfers', function (Blueprint $table) {
            $table->string('dispatch_method', 32)->nullable()->after('copy_purpose');
            $table->string('dispatch_reference')->nullable()->after('dispatch_method');
            $table->string('release_receipt_reference')->nullable()->after('dispatch_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_transfers', function (Blueprint $table) {
            $table->dropColumn([
                'dispatch_method',
                'dispatch_reference',
                'release_receipt_reference',
            ]);
        });
    }
};
