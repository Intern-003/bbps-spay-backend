<?php

namespace App\Http\Controllers\SelfMerchantOnboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Throwable;

class SelfMerchantOnboardController extends Controller
{
    public function selfMerchantOnboardProcess(Request $request)
    {
        try {
            // dd("selfMerchantOnboardProcess");
            // Validate main merchant fields
            $validatedData = $request->validate([
                'scheme_id'             => 'nullable|integer',
                'name'                  => 'required|string|max:255',
                // 'credentials_id'        => 'nullable|integer',
                'email'                 => 'required|email|unique:users,email',
                'mobile_no'             => 'required|string|max:15|unique:users,mobile_no',
                'password'              => 'nullable|string|min:6',
                'business_mcc'          => 'nullable|string|max:50',
                'company_type'          => 'nullable|in:proprietary,partnership,private,public,llp,society,trust,govt,huf,boi,aop,ajp',
                'company_pan_no'        => 'nullable|string|max:20',
                'company_pan_no_doc'    => 'nullable|file',
                'company_gst_no'        => 'nullable|string|max:20',
                'company_gst_no_doc'    => 'nullable|file',
                'cancel_cheque_doc'     => 'nullable|file',
                'cin_llpin'             => 'nullable|string|max:50',
                'date_of_incorporation' => 'nullable|date',
                'account_holder_name'   => 'nullable|string|max:255',
                'bank_account_no'       => 'nullable|string|max:50',
                'ifsc_code'             => 'nullable|string|max:20',
                'address'               => 'nullable|string|max:255',
                'city'                  => 'nullable|string|max:100',
                'district'              => 'nullable|string|max:100',
                'state'                 => 'nullable|string|max:100',
                'pin_code'              => 'nullable|string|max:10',
                'website_url'           => 'nullable|url|max:255',
                'description'           => 'nullable|string|max:500',
                'director_info'         => 'nullable|array',
                'director_info.*.director_name'     => 'required_with:director_info|string|max:255',
                'director_info.*.director_pan_no'   => 'required_with:director_info|string|max:20',
                'director_info.*.director_aadhar_no'=> 'required_with:director_info|string|max:20',
                'director_info.*.director_gender'   => 'required_with:director_info|string|in:male,female,other',
                'director_info.*.director_dob'      => 'required_with:director_info|date',
                'director_info.*.user_pan_doc'      => 'nullable|file',
                'director_info.*.user_addhar_doc'   => 'nullable|file',
            ]);
    
            // Encrypt password if not provided
            $validatedData['password'] = bcrypt($validatedData['password'] ?? $validatedData['mobile_no']);
    
            // Handle main merchant file uploads
            $fileFields = [
                'company_pan_no_doc' => 'company_pan_docs',
                'company_gst_no_doc' => 'company_gst_docs',
                'cancel_cheque_doc'  => 'cancel_cheque_docs',
            ];
    
            foreach ($fileFields as $field => $folder) {
                if ($request->hasFile($field)) {
                    $path = $request->file($field)->store($folder, 'public');
                    $validatedData[$field] = storage_path('app/public/' . $path);
                } else {
                    $validatedData[$field] = null;
                }
            }
    
            // Handle director files
            $directors = [];
            if (!empty($validatedData['director_info'])) {
                foreach ($validatedData['director_info'] as $index => $director) {
                    // For each director, store files if uploaded
                    if ($request->hasFile("director_info.$index.user_pan_doc")) {
                        $director['user_pan_doc'] = storage_path(
                            'app/public/' . $request->file("director_info.$index.user_pan_doc")->store('director_pan_docs', 'public')
                        );
                    }
    
                    if ($request->hasFile("director_info.$index.user_addhar_doc")) {
                        $director['user_addhar_doc'] = storage_path(
                            'app/public/' . $request->file("director_info.$index.user_addhar_doc")->store('director_aadhar_docs', 'public')
                        );
                    }
    
                    $directors[] = $director;
                }
            }
    
            $validatedData['director_info'] = $directors;
    
            // Create merchant
            $merchant = User::create($validatedData);
            // dd($merchant);
    
            return response()->json([
                'success'  => true,
                'message'  => 'Merchant onboarded successfully',
                'merchant' => $merchant,
            ], 201);
    
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error_code' => 422,
                'message'    => 'Validation failed',
                'errors'     => $e->errors()
            ], 422);
        }
}

    public function registerNewMerchant(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'name'       => 'required|string',
                'email'      => 'required|email|unique:users,email',
                'mobile_no'  => 'required|digits:10|unique:users,mobile_no',
                'password'  => 'nullable|string|min:6',
            ]);
            
            User::create([
                'name'           => $validatedData['name'],
                'email'          => $validatedData['email'],
                'mobile_no'      => $validatedData['mobile_no'],
                'password'       => Hash::make($validatedData['mobile_no']), // safer
                'account_status' => 1,
            ]);
    
            return response()->json([
                'registered' => true,
                'message'    => 'Merchant registered successfully'
            ], 201);
    
        } catch (ValidationException $e) {
            return response()->json([
                'registered' => false,
                'errors'     => $e->errors()
            ], 422);
    
        } catch (Throwable $e) {
            return response()->json([
                'registered' => false,
                'message'    => 'Something went wrong while registering merchant'
            ], 500);
        }
    }
    
    public function onboardUser(Request $request)
    {
    try {
        // dd("sheesha");
        // Fetch user by email or mobile_no
        if ($request->has('email')) {
            $user = User::where('email', $request->email)->first();
        } elseif ($request->has('mobile_no')) {
            $user = User::where('mobile_no', $request->mobile_no)->first();
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Email or Mobile number is required to identify user'
            ], 400);
        }

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Validation
        $validatedData = $request->validate([
            'scheme_id'              => 'nullable|integer',
            'name'                   => 'nullable|string|max:255',
            'credentials_id'         => 'nullable|integer|exists:credentials,id',
            'email'                  => 'nullable|email|unique:users,email,' . $user->id,
            'mobile_no'              => 'nullable|string|max:15|unique:users,mobile_no,' . $user->id,
            'password'               => 'nullable|string|min:6',
            'business_mcc'           => 'nullable|string|max:50',
            'company_type'           => 'nullable|string|max:100',
            'company_pan_no'         => 'nullable|string|max:20',
            'company_gst_no'         => 'nullable|string|max:20',
            'cin_llpin'              => 'nullable|string|max:50',
            'date_of_incorporation'  => 'nullable|date',
            'account_holder_name'    => 'nullable|string|max:255',
            'bank_account_no'        => 'nullable|string|max:50',
            'ifsc_code'              => 'nullable|string|max:20',
            'address'                => 'nullable|string|max:255',
            'city'                   => 'nullable|string|max:100',
            'district'               => 'nullable|string|max:100',
            'state'                  => 'nullable|string|max:100',
            'pin_code'               => 'nullable|string|max:10',

            // FILES
            'company_pan_no_doc'     => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'company_gst_no_doc'     => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'cancel_cheque_doc'      => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'video_kyc'              => 'nullable|file|mimes:mp4,mov,avi|max:51200', // 50MB max

            // DIRECTOR INFO
            'director_info'                               => 'nullable|array',
            'director_info.*.director_name'               => 'nullable|string|max:255',
            'director_info.*.director_pan_no'             => 'nullable|string|max:20',
            'director_info.*.director_aadhar_no'          => 'nullable|string|max:20',
            'director_info.*.director_gender'             => 'nullable|string|in:male,female,other',
            'director_info.*.director_dob'                => 'nullable|date',
            'director_info.*.user_pan_doc'                => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'director_info.*.user_addhar_doc'             => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',

            'website_url'            => 'nullable|url|max:255',
            'description'            => 'nullable|string|max:500',
        ]);

        // Update password if provided
        if (!empty($validatedData['password'])) {
            $validatedData['password'] = Hash::make($validatedData['password']);
        }

        // Update user fields
        $user->fill($validatedData);

        // Handle company files
        foreach (['company_pan_no_doc', 'company_gst_no_doc', 'cancel_cheque_doc'] as $fileKey) {
            if ($request->hasFile($fileKey)) {
                $path = $request->file($fileKey)->store('merchant_docs', 'public');
                $user->{$fileKey} = $path;
            }
        }

        // Handle director info files
        if ($request->has('director_info')) {
            $directors = $request->input('director_info');

            foreach ($directors as $i => $dir) {
                $existingDirector = $user->director_info[$i] ?? [];

                if ($request->hasFile("director_info.$i.user_pan_doc")) {
                    $dir["user_pan_doc"] = $request->file("director_info.$i.user_pan_doc")->store('director_docs', 'public');
                } else {
                    $dir["user_pan_doc"] = $existingDirector["user_pan_doc"] ?? null;
                }

                if ($request->hasFile("director_info.$i.user_addhar_doc")) {
                    $dir["user_addhar_doc"] = $request->file("director_info.$i.user_addhar_doc")->store('director_docs', 'public');
                } else {
                    $dir["user_addhar_doc"] = $existingDirector["user_addhar_doc"] ?? null;
                }

                foreach (['director_name','director_pan_no','director_aadhar_no','director_gender','director_dob'] as $field) {
                    $dir[$field] = $dir[$field] ?? ($existingDirector[$field] ?? null);
                }

                $directors[$i] = $dir;
            }

            $user->director_info = $directors;
        }

        // Handle Video KYC
        if ($request->hasFile('video_kyc')) {
            $path = $request->file('video_kyc')->store('video_kyc', 'public');
            $user->video_kyc = $path;
        }

        $user->kyc_status = "pending";

        $user->save();

        return response()->json([
            'status'  => true,
            'message' => 'User updated successfully',
            'data'    => $user,
        ]);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'status'  => false,
            'message' => 'Validation failed',
            'errors'  => $e->errors(),
        ], 422);

    } catch (\Exception $e) {
        \Log::error("Error updating user: " . $e->getMessage());

        return response()->json([
            'status'  => false,
            'message' => 'Something went wrong while updating user',
            'error'   => $e->getMessage(),
        ], 500);
    }
}

    function updateMerchantKyc(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'id'                => 'required|exists:users,id',
                'scheme_id'         => 'nullable|integer',
                'credentials_id'    => 'nullable|integer',
                'reject'            => 'nullable|boolean', // NEW: Reject flag
            ]);
    
            // Fetch merchant
            $merchant = User::findOrFail($validatedData['id']);
    
            // ❌ Stop if pre-KYC is not completed
            if ($merchant->kyc_status !== "pending") {
                return response()->json([
                    'message' => 'Pre-KYC not completed. Cannot approve/reject KYC.',
                ], 400);
            }
    
            // Remove id and reject from validated data so it doesn't get updated
            unset($validatedData['id'], $validatedData['reject']);
    
            // Update merchant fields
            $merchant->update($validatedData);
    
            // ✅ Handle approve or reject
            if (!empty($request->reject) && $request->reject == true) {
                $merchant->kyc_status = "approved";          // Not approved
                $merchant->kyc_status = "rejected";   // Mark as rejected
                $merchant->save();
    
                return response()->json([
                    'message'  => 'KYC rejected successfully',
                    'merchant' => $merchant,
                ], 200);
            } else {
                $merchant->kyc_status = "approved";          // Approve
                $merchant->kyc_status = "rejected";   // Clear rejected flag if any
                $merchant->save();
    
                return response()->json([
                    'message'  => 'KYC approved successfully',
                    'merchant' => $merchant,
                ], 200);
            }
    
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error_code' => 422,
                'message'    => 'Validation failed',
                'errors'     => $e->errors(),
            ], 422);
        }
    }
    
}
