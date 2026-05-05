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
        Schema::create('transaction_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('bill_payment_id')->constrained('bill_payment')->onDelete('cascade');
            $table->string('response_reason')->nullable();
            $table->string('txn_status')->nullable();
            $table->string('mobile')->nullable();
            $table->string('amount')->nullable();
            $table->string('biller_id')->nullable();
            $table->string('txn_reference_id')->nullable();
            $table->string('agent_id')->nullable();
            $table->string('txn_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_status');
    }
};
