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
        Schema::table('bharat_connect_mdm', function (Blueprint $table) {
            $table->json('biller_response')->nullable()->after('blr_coverage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bharat_connect_mdm', function (Blueprint $table) {
            $table->dropColumn('biller_response');
        });
    }
};
