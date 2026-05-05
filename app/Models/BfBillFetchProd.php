<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BfBillFetchProd extends Model
{
    use HasFactory;

    protected $table = 'bf_bill_fetch_prod';

    protected $fillable = [
        'user_id',
        'blr_id',
        'request_id',
        'bill_fetch_response',
        'input_params',
        'biller_response',
        'additional_info',
    ];

    protected $casts = [
        'bill_fetch_response' => 'array',
        'input_params' => 'array',
        'biller_response' => 'array',
        'additional_info' => 'array',
    ];

    public function bharatConnect()
    {
        return $this->belongsTo(BharatConnectMdm::class, 'blr_id', 'blr_id');
    }
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
}
