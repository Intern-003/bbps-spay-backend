<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillFetches extends Model
{
    use HasFactory;

    protected $table = 'bill_fetches';
    protected $fillable = ['request_id', 'biller_infos_id', 'biller_response', 'additional_info', 'input_params'];

    public function biller()
    {
        return $this->belongsTo(BillerInfo::class, 'biller_infos_id');
    }
    
}
