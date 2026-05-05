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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('mobile')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->decimal('charge', 15, 2)->nullable();
            $table->decimal('profit', 15, 2)->nullable();
            $table->decimal('gst', 15, 2)->nullable();
            $table->decimal('tds', 15, 2)->nullable();
            $table->string('spay_txn_id')->nullable();
            $table->string('request_id')->nullable();
            $table->string('payment_ref_id')->nullable();
            $table->text('description')->nullable();
            $table->text('remark')->nullable();
            $table->string('option1')->nullable();
            $table->string('option2')->nullable();
            $table->string('option3')->nullable();
            $table->string('option4')->nullable();
            $table->enum('status', ['initiated', 'pending', 'success', 'failed', 'reversed', 'refunded', 'complete'])->nullable();
            $table->enum('payment_platform', ['agt_portal'])->nullable();
            $table->decimal('payout_amount', 15, 2)->nullable();
            $table->string('payout_opening_balance')->nullable();
            $table->string('payout_closing_balance')->nullable();
            $table->enum('payment_mode', ['cash'])->nullable();
            $table->enum('payment_channel', ['agt'])->nullable();
            $table->enum('transtion_type', ['credit', 'debit'])->nullable();
            $table->enum('product_type', ['load_wallet','merchant_transaction'])->nullable();
            $table->decimal('commission_inc_gst', 15, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
