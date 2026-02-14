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
            $table->boolean('is_returnable')->default(false)->after('metadata');
            $table->date('return_deadline')->nullable()->after('is_returnable');
            $table->timestamp('returned_at')->nullable()->after('return_deadline');
            $table->string('returned_to')->nullable()->after('returned_at');
            $table->foreignId('original_current_department_id')->nullable()->after('returned_to')->constrained('departments')->nullOnDelete();
            $table->foreignId('original_custodian_user_id')->nullable()->after('original_current_department_id')->constrained('users')->nullOnDelete();
            $table->string('original_physical_location')->nullable()->after('original_custodian_user_id');

            $table->index('return_deadline');
            $table->index('returned_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['return_deadline']);
            $table->dropIndex(['returned_at']);
            $table->dropConstrainedForeignId('original_custodian_user_id');
            $table->dropConstrainedForeignId('original_current_department_id');
            $table->dropColumn([
                'is_returnable',
                'return_deadline',
                'returned_at',
                'returned_to',
                'original_physical_location',
            ]);
        });
    }
};
