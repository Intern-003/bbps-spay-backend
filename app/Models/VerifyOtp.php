<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VerifyOtp extends Model
{
    use HasFactory;
    
    protected $table = 'verify_otps';
    
    protected $fillable = ['verify_type', 'value', 'unique_otp', 'expires_at'];
}
