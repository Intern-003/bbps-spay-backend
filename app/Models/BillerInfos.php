<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillerInfos extends Model
{
    use HasFactory;
    protected $table = 'biller_infos';
    protected $fillable = [
        'biller_id',
        'param_name',
        'data_type',
        'is_optional',
        'min_length',
        'max_length',
        'reg_ex',
        'visibility',
        'biller_adhoc'
    ];

    public function billFetches()
    {
        return $this->hasMany(BillFetch::class, 'biller_infos_id');
    }
}
