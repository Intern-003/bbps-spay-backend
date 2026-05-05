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
        Schema::create('biller_information', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); 
            $table->string('biller_id')->nullable();
            $table->string('biller_name')->nullable();
            $table->string('biller_category')->nullable();
            $table->string('biller_adhoc')->nullable();
            $table->string('biller_coverage')->nullable();
            $table->string('biller_fetch_requiremet')->nullable();
            $table->json('biller_input_params')->nullable();
            $table->json('biller_additional_info')->nullable();
            $table->json('biller_amount_options')->nullable();
            $table->json('biller_payment_modes')->nullable();
            $table->json('biller_payment_channels')->nullable();
            $table->longText('biller_description')->nullable();
            $table->string('biller_alias_name')->nullable();
            $table->string('biller_payment_exactness')->nullable();
            $table->string('biller_support_bill_validation')->nullable();
            $table->string('support_pending_status')->nullable();
            $table->string('support_deemed')->nullable();
            $table->string('biller_status')->nullable();
            $table->string('biller_timeout')->nullable();
            $table->json('recharge_amount_in_validation_request')->nullable();
            $table->json('biller_additional_info_payment')->nullable();
            $table->json('plan_additional_info')->nullable();
            $table->string('plan_mdm_requirement')->nullable();
            $table->string('biller_response_type')->nullable();
            $table->json('biller_plan_response_params')->nullable();
            $table->json('interchange_fee_CCF1')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('biller_information');
    }
};
