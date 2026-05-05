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
        Schema::create('bf_bill_fetch', function (Blueprint $table) {
            $table->id();
            $table->string('blr_id')->nullable();
            $table->string('request_id')->nullable();
            $table->json('bill_fetch_response')->nullable();
            $table->json('input_params')->nullable();
            $table->json('biller_response')->nullable();
            $table->json('additional_info')->nullable();
            $table->timestamps();

            $table->foreign('blr_id')
                  ->references('blr_id')
                  ->on('bharat_connect_mdm_test')
                  ->onDelete('set null')
                  ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bf_bill_fetch');
    }
};
