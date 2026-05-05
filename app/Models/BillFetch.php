<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillFetch extends Model
{
    use HasFactory;
    
    protected $table = 'bill_fetch';
    
    protected $fillable = [
        'user_id',
        'biller_information_id',
        'input_params',
        'biller_response',
        'additional_info',
    ];
    
    protected $casts = [
        'input_params'    => 'array',
        'biller_response' => 'array',
        'additional_info' => 'array',
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function billerInformation()
    {
        return $this->belongsTo(BillerInformation::class);
    }
    
    public function billPayments()
    {
        return $this->hasMany(BillPayment::class);
    }

}
