<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BpBillPayment extends Model
{
    use HasFactory;
    protected $table = 'bp_bill_payments';

    protected $fillable = [
        'user_id',
        'blr_id',
        'request_id',
        'txnRefID',
        'pay_response',
        'mobile_no',
        'txnStatus',
    ];

    protected $casts = [
        'pay_response' => 'array',
      
    ];

    public function bharatConnect()
    {
        return $this->belongsTo(BharatConnectMdmTest::class, 'blr_id', 'blr_id');
    }
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
