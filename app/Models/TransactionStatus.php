<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionStatus extends Model
{
    use HasFactory;
    
    protected $table = 'transaction_status';
    
    protected $fillable = [
        'user_id',
        'bill_payment_id',
        'response_reason',
        'txn_status',
        'mobile',
        'amount',
        'biller_id',
        'txn_reference_id',
        'agent_id',
        'txn_date',
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function billPayment()
    {
        return $this->belongsTo(BillPayment::class);
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
