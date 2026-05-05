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
        Schema::create('bill_fetches', function (Blueprint $table) {
            $table->id();
            $table->string('request_id')->nullable();
            $table->foreignId('biller_infos_id')
                ->constrained('biller_infos')
                ->onDelete('cascade');
            $table->json('biller_response')->nullable();
            $table->json('additional_info')->nullable();
            $table->json('input_params')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bill_fetches');
    }
};
