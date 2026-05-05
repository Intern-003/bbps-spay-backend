<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillerInformation extends Model
{
    use HasFactory;

    protected $table = 'biller_information';

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
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function billFetches()
    {
        return $this->hasMany(BillFetch::class);
    }

    public function billPayments()
    {
        return $this->hasMany(BillPayment::class);
    }
}
