<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanPullMdmTest extends Model
{
    use HasFactory;
    
    protected $table = 'plan_pull_mdm_test';
    
    protected $fillable = [
        'plan_biller_id',
        'plan_biller_response'
    ];
    
    protected $casts = [
        'plan_biller_response' => 'array',
    ];

}
