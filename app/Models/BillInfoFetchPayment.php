<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillInfoFetchPayment extends Model
{
    use HasFactory;

    protected $table = 'bill_info_fetch_payment';

    // Mass assignable attributes
    protected $fillable = [
        'user_id',
        'biller_id',
        'biller_name',
        'biller_category',
        'biller_adhoc',
        'biller_coverage',
        'biller_fetch_requiremet',
        'biller_input_params',
        'biller_additional_info',
        'biller_amount_options',
        'biller_payment_modes',
        'biller_payment_channels',
        'biller_description',
        'biller_alias_name',
        'biller_payment_exactness',
        'biller_support_bill_validation',
        'support_pending_status',
        'support_deemed',
        'biller_status',
        'biller_timeout',
        'recharge_amount_in_validation_request',
        'biller_additional_info_payment',
        'plan_additional_info',
        'plan_mdm_requirement',
        'biller_response_type',
        'biller_plan_response_params',
        'interchange_fee_CCF1',
        'fetch_input_params',
        'biller_response',
        'additional_info',
        'txn_ref_id',
        'request_id',
        'resp_amount',
        'response_reason',
        'txn_resp_type',
        'resp_bill_number',
        'approval_ref_number',
        'payment_input_params',
        'cust_conv_fee',
        'resp_bill_date',
        'resp_bill_period',
        'resp_customer_name',
        'resp_due_date',
    ];
    
        protected $casts = [
        'biller_input_params' => 'array',
        'biller_additional_info' => 'array',
        'biller_amount_options' => 'array',
        'biller_payment_modes' => 'array',
        'biller_payment_channels' => 'array',
        'recharge_amount_in_validation_request' => 'array',
        'biller_additional_info_payment' => 'array',
        'plan_additional_info' => 'array',
        'biller_plan_response_params' => 'array',
        'interchange_fee_CCF1' => 'array',
        'fetch_input_params' => 'array',
        'biller_response' => 'array',
        'additional_info' => 'array',
        'payment_input_params' => 'array',
    ];
    
        public function user()
    {
        return $this->belongsTo(User::class);
    }
    
}
