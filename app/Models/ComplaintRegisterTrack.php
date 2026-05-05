<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComplaintRegisterTrack extends Model
{
    use HasFactory;

    // Explicit table name because it's not plural
    protected $table = 'complaint_register_track';

    // Allow mass assignment for these columns
 protected $fillable = [
        'user_id',
        'complaint_type',
        'participation_type',
        'biller_id',
        'txn_ref_id',
        'complaint_desc',
        'serv_reason',
        'complaint_disposition',
        'complaint_assigned',
        'register_complaint_id',
        'register_response_reason',
        'track_complaint_id',
        'complaint_remarks',
        'track_response_reason',
        'complaint_status',
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
