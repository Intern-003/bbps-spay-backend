<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Scheme extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'blr_id',
        'name',
        'commission_type',
        'type',
        'commission_value',
        'status',
        'gst_type',
        'gst_value',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
