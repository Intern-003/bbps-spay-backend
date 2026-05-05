<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserCategoryPermission extends Model
{
    use HasFactory;

 protected $fillable = [
    'user_id',
    'categories'
];

protected $casts = [
    'categories' => 'array'
];


    // Optional: user relation
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
