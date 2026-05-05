<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillPayment extends Model
{
    use HasFactory;
    
    protected $table = 'bill_payment';
    
    protected $fillable = [
        'user_id',
        'biller_information_id',
        'bill_fetch_id',
        'txn_ref_id',
        'request_id',
        'resp_amount',
        'response_reason',
        'txn_resp_type',
        'resp_bill_number',
        'approval_ref_number',
        'input_params',
        'cust_conv_fee',
        'resp_bill_date',
        'resp_bill_period',
        'resp_customer_name',
        'resp_due_date',
    ];
    
    protected $casts = [
        'input_params' => 'array',
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function billerInformation()
    {
        return $this->belongsTo(BillerInformation::class);
    }

    public function billFetch()
    {
        return $this->belongsTo(BillFetch::class);
    }

    public function transactionStatuses()
    {
        return $this->hasMany(TransactionStatus::class);
    }

    public function complaintRegisters()
    {
        return $this->hasMany(ComplaintRegister::class);
    }

    public function complaintTracks()
    {
        return $this->hasMany(ComplaintTrack::class);
    }

}
