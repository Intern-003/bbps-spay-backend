<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComplaintRegister extends Model
{
    use HasFactory;
    
    protected $table = 'complaint_register';

    protected $fillable = [
        'user_id',
        'bill_payment_id',
        'transaction_status_id',
        'response_reason',
        'complaint_id',
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
    
    public function complaintTracks()
    {
        return $this->hasMany(ComplaintTrack::class);
    }
    
}
