<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'mobile',
        'amount',
        'charge',
        'profit',
        'gst',
        'tds',
        'spay_txn_id',
        'request_id',
        'payment_ref_id',
        'description',
        'remark',
        'option1',
        'option2',
        'option3',
        'option4',
        'status',
        'payment_platform',
        'payout_amount',
        'payout_opening_balance',
        'payout_closing_balance',
        'payment_mode',
        'payment_channel',
        'transtion_type',
        'product_type',
        'commission_inc_gst',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
