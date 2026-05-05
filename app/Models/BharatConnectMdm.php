<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BharatConnectMdm extends Model
{
    use HasFactory;
    
    protected $primaryKey = 'blr_id';
    public $incrementing = false;
    protected $keyType = 'string';
   
    protected $table = 'bharat_connect_mdm';
    
    protected $fillable = ['blr_id', 'blr_name', 'blr_alias_name', 'blr_category_name', 'blr_coverage', 'biller_response'];
    
    protected $casts = [
        'biller_response' => 'array',
    ];
    
    public function billFetches()
    {
        return $this->hasMany(BfBillFetchProd::class, 'blr_id', 'blr_id');
    }
}
