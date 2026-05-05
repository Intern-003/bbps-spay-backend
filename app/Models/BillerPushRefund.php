<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillerPushRefund extends Model
{
    use HasFactory;

    protected $table = 'biller_push_refunds';

    protected $fillable = [
        'user_id',
        'response_json',
    ];

    protected $casts = [
        'response_json' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withDefault();
    }
    
}
