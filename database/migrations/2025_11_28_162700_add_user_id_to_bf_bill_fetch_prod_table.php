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
        Schema::table('bf_bill_fetch_prod', function (Blueprint $table) {
            // Add user_id column (nullable, if needed)
            $table->unsignedBigInteger('user_id')->nullable()->after('id');

            // Add foreign key constraint
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null')
                  ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bf_bill_fetch_prod', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['user_id']);

            // Drop column
            $table->dropColumn('user_id');
        });
    }
};
