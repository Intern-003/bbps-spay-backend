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
        Schema::table('users', function (Blueprint $table) {
            $table->string('mobile_no')->nullable()->after('password');
            $table->string('business_mcc')->nullable()->after('mobile_no');
            $table->enum('company_type', ['proprietary', 'partnership', 'private', 'public', 'llp', 'society', 'trust', 'govt', 'huf', 'boi', 'aop', 'ajp'])->nullable()->after('business_mcc');
            $table->string('company_pan_no')->nullable()->after('company_type');
            $table->string('company_pan_no_doc')->nullable()->after('company_pan_no');
            $table->string('company_gst_no')->nullable()->after('company_pan_no_doc');
            $table->string('company_gst_no_doc')->nullable()->after('company_gst_no');
            $table->string('cin_llpin')->nullable()->after('company_gst_no_doc');
            $table->date('date_of_incorporation')->nullable()->after('cin_llpin');
            $table->string('account_holder_name')->nullable()->after('date_of_incorporation');
            $table->string('bank_account_no')->nullable()->after('account_holder_name');
            $table->string('ifsc_code')->nullable()->after('bank_account_no');
            $table->string('cancel_cheque_doc')->nullable()->after('ifsc_code');
            $table->string('address')->nullable()->after('cancel_cheque_doc');
            $table->string('city')->nullable()->after('address');
            $table->string('district')->nullable()->after('city');
            $table->string('state')->nullable()->after('district');
            $table->string('pin_code')->nullable()->after('state');
            $table->json('director_info')->nullable()->after('pin_code');
            $table->string('website_url')->nullable()->after('director_info');
            $table->boolean('account_status')->default(0)->nullable()->after('website_url');
            $table->string('merchant_bbps_wallet')->nullable()->after('account_status');
            $table->string('total_charges')->nullable()->after('merchant_bbps_wallet');
            $table->string('remark')->nullable()->after('total_charges');
            $table->string('description')->nullable()->after('remark');
            $table->string('kyc_status')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
