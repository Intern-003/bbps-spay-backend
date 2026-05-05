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
        Schema::create('complaint_register', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('bill_payment_id')->constrained('bill_payment')->onDelete('cascade');
            $table->foreignId('transaction_status_id')->constrained('transaction_status')->onDelete('cascade');
            $table->string('response_reason')->nullable();
            $table->string('complaint_id')->nullable();
            $table->string('complaint_assigned')->nullable();
            $table->string('response_code')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaint_register');
    }
};
