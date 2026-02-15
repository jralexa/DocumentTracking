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
            $table->string('forward_version_type')->nullable()->after('remarks')->index();
            $table->boolean('copy_kept')->default(false)->after('forward_version_type');
            $table->string('copy_storage_location')->nullable()->after('copy_kept');
            $table->text('copy_purpose')->nullable()->after('copy_storage_location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_transfers', function (Blueprint $table) {
            $table->dropColumn([
                'forward_version_type',
                'copy_kept',
                'copy_storage_location',
                'copy_purpose',
            ]);
        });
    }
};
