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
        Schema::create('bill_payment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('biller_information_id')->constrained('biller_information')->onDelete('cascade');
            $table->foreignId('bill_fetch_id')->constrained('bill_fetch')->onDelete('cascade');
            $table->string('txn_ref_id')->nullable();
            $table->string('request_id')->nullable();
            $table->string('resp_amount')->nullable();
            $table->string('response_reason')->nullable();
            $table->string('txn_resp_type')->nullable();
            $table->string('resp_bill_number')->nullable();
            $table->string('approval_ref_number')->nullable();
            $table->json('input_params')->nullable();
            $table->string('cust_conv_fee')->nullable();
            $table->string('resp_bill_date')->nullable();
            $table->string('resp_bill_period')->nullable();
            $table->string('resp_customer_name')->nullable();
            $table->string('resp_due_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bill_payment');
    }
};
