<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComplaintTrack extends Model
{
    use HasFactory;

    protected $table = 'complaint_track';

    protected $fillable = [
        'user_id',
        'bill_payment_id',
        'transaction_status_id',
        'complaint_register_id',
        'response_reason',
        'complaint_id',
        'complaint_status',
        'complaint_remarks',
        'complaint_assigned',
        'response_code',
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function billPayment()
    {
        return $this->belongsTo(BillPayment::class);
    }
    
    public function transactionStatus()
    {
        return $this->belongsTo(TransactionStatus::class);
    }
    
    public function complaintRegister()
    {
        return $this->belongsTo(ComplaintRegister::class);
    }
    
}
