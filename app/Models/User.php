<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'scheme_id',
        'name',
        'email',
        'password',
        "customer_mobile",
        "customer_email",
        "customer_adhaar",
        "customer_pan",
        'role_id',
        'mobile_no',
        'business_mcc',
        'company_type',
        'company_pan_no',
        'company_pan_no_doc',
        'company_gst_no',
        'company_gst_no_doc',
        'cin_llpin',
        'date_of_incorporation',
        'account_holder_name',
        'bank_account_no',
        'ifsc_code',
        'cancel_cheque_doc',
        'address',
        'city',
        'district',
        'state',
        'pin_code',
        'director_info',
        'website_url',
        'account_status',
        'merchant_bbps_wallet',
        'total_charges',
        'remark',
        'description',
        'kyc_status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'director_info' => 'array',
        'date_of_incorporation' => 'date',
        'account_status' => 'boolean',
    ];
    
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
    
    public function scheme()
    {
        return $this->belongsTo(Scheme::class, 'scheme_id');
    }
   
    // A user has many biller_information entries
    public function billerInformation()
    {
        return $this->hasMany(BillerInformation::class);
    }

    // A user has many bill_fetch entries
    public function billFetches()
    {
        return $this->hasMany(BillFetch::class);
    }
    
    public function billInfoFetchPayments()
    {
        return $this->hasMany(BillInfoFetchPayment::class);
    }
    
    public function report()
    {
        return $this->hasMany(Report::class);
    }
    
    public function bfBillFetch()
    {
        return $this->hasMany(BfBillFetch::class);
    }
    
    public function bfBillFetchProd()
    {
        return $this->hasMany(BfBillFetchProd::class);
    }
    
    public function bpBillPayment()
    {
        return $this->hasMany(BpBillPayment::class);
    }
    
    public function bpBillPaymentProd()
    {
        return $this->hasMany(BpBillPaymentProd::class);
    }
    
    public function complaintRegisterTrack()
    {
        return $this->hasMany(ComplaintRegisterTrack::class);
    }
    
    public function complaintRegisterTrackProd()
    {
        return $this->hasMany(ComplaintRegisterTrackProd::class);
    }
    
    public function allowedCategories()
    {
    return $this->hasMany(UserCategoryPermission::class, 'user_id');
    }
    
    public function billerPushRefunds()
    {
    return $this->hasMany(BillerPushRefund::class);
    }


}
