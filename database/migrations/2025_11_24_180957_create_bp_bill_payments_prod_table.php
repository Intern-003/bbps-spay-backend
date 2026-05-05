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
        Schema::create('bp_bill_payments_prod', function (Blueprint $table) {
            $table->id();
            $table->string('blr_id')->nullable();
            $table->string('request_id')->nullable();
            $table->string('txnRefID')->nullable();
            $table->string('mobile_no')->nullable();
            $table->json('pay_response')->nullable();
            $table->string('txnStatus')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bp_bill_payments_prod');
    }
};
