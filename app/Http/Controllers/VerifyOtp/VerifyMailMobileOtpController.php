<?php

namespace App\Http\Controllers\VerifyOtp;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\VerifyOtp;
use Illuminate\Support\Carbon;

class VerifyMailMobileOtpController extends Controller
{

    public function sendMobileOtp(Request $request)
    {
        // dd("sendMobileOtp");
        $request->validate([
            'mobile_no' => 'required|digits:10'
        ]);

        $otp = rand(100000, 999999);

        VerifyOtp::updateOrCreate(
            ['verify_type' => 'mobile', 'value' => $request->mobile_no],
            [
                'unique_otp' => $otp,
                'expires_at' => Carbon::now()->addMinutes(5),
            ]
        );

        return response()->json([
            'status' => true,
            'message' => 'Mobile OTP sent',
            'otp_for_testing' => $otp
        ]);
    }
    
    public function verifyMobileOtp(Request $request)
    {
        // dd("verifyMobileOtp");
        $validatedData = $request->validate([
            'mobile_no' => 'required|digits:10',
            'unique_otp' => 'required|digits:6'
        ]);

        $otp = VerifyOtp::where([
            'verify_type' => 'mobile',
            'value' => $request->mobile_no,
            'unique_otp' => $request->unique_otp
        ])->where('expires_at', '>=', now())->first();

        if (!$otp) {
            return response()->json(['message' => 'Invalid or expired OTP'], 400);
        }

        $otp->delete();

        return response()->json(['verified' => true]);
    }
    
    public function sendMailOtp(Request $request)
    {
        // dd("sendMailOtp");
        $request->validate([
            'email' => 'required|email'
        ]);

        $otp = rand(100000, 999999);
        // dd($otp);

        VerifyOtp::updateOrCreate(
            ['verify_type' => 'email', 'value' => $request->email],
            [
                'unique_otp' => $otp,
                'expires_at' => Carbon::now()->addMinutes(5),
            ]
        );

        return response()->json([
            'status' => true,
            'message' => 'Email OTP sent',
            'otp_for_testing' => $otp
        ]);
    }
    
    public function verifyMailOtp(Request $request)
    {
        // dd("verifyMailOtp");
        $validatedData = $request->validate([
            'email' => 'required|email',
            'unique_otp' => 'required|digits:6'
        ]);

        $otp = VerifyOtp::where([
            'verify_type' => 'email',
            'value' => $request->email,
            'unique_otp' => $request->unique_otp
        ])->where('expires_at', '>=', now())->first();

        if (!$otp) {
            return response()->json(['message' => 'Invalid or expired OTP'], 400);
        }

        $otp->delete();

        return response()->json(['verified' => true]);
    }
    
    public function flutterTest(Request $request){
        return response()->json([
            'status' => true,
            'message' => 'flutterTest'
        ]);
    }
    
}
