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
        Schema::create('bharat_connect_mdm_test', function (Blueprint $table) {
            $table->string('blr_id')->primary();
            $table->string('blr_name')->nullable();
            $table->string('blr_alias_name')->nullable();
            $table->string('blr_category_name')->nullable();
            $table->string('blr_coverage')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bharat_connect_mdm_test');
    }
};
