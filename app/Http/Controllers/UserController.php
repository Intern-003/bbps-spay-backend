<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Services\CommonSecurityService;
use App\Models\Report;


class UserController extends Controller
{
    public function getMerchants(Request $request)
    {
        // dd("getMerchants");
        $query = User::where('role_id', 2);
    
        // Apply filters only if provided in the request
        if ($request->has('account_status') && $request->account_status !== '') {
            $query->where('account_status', (bool)$request->account_status);
        }
    
        // Fetch results (you can later add pagination if needed)
        $merchants = $query->orderBy('id', 'desc')->get();
    
        return response()->json([
            'status' => true,
            'message' => 'Merchant list fetched successfully',
            'data' => $merchants,
        ]);
}

    public function onboardMerchant(Request $request)
    {
        try {
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

    public function showMerchant($id)
    {
        // Fetch merchant with role_id = 2
        $merchant = User::where('role_id', 2)->where('id', $id)->first();
    
        // If not found
        if (!$merchant) {
            return response()->json([
                'status' => false,
                'message' => 'Merchant not found',
            ], 404);
        }
    
        // Success response
        return response()->json([
            'status' => true,
            'message' => 'Merchant details fetched successfully',
            'data' => $merchant,
        ]);
    }
    
    public function updateMerchant(Request $request)
    {
        // dd("test");
        try {
            // Validation rules
            $validatedData = $request->validate([
                'id'                    => 'required|integer',
                'scheme_id'             => 'nullable|integer',
                'name'                  => 'sometimes|string|max:255',
                'email'                 => 'sometimes|email|unique:users,email,' . $request->id,
                'mobile_no'             => 'sometimes|string|max:15|unique:users,mobile_no,' . $request->id,
                'password'              => 'sometimes|string|min:6|nullable',
                'business_mcc'          => 'sometimes|string|max:50|nullable',
                'company_type'          => 'sometimes|in:proprietary,partnership,private,public,llp,society,trust,govt,huf,boi,aop,ajp|nullable',
                'company_pan_no'        => 'sometimes|string|max:20|nullable',
                'company_pan_no_doc'    => 'sometimes|file|nullable',
                'company_gst_no'        => 'sometimes|string|max:20|nullable',
                'company_gst_no_doc'    => 'sometimes|file|nullable',
                'cancel_cheque_doc'     => 'sometimes|file|nullable',
                'cin_llpin'             => 'sometimes|string|max:50|nullable',
                'date_of_incorporation' => 'sometimes|date|nullable',
                'account_holder_name'   => 'sometimes|string|max:255|nullable',
                'bank_account_no'       => 'sometimes|string|max:50|nullable',
                'ifsc_code'             => 'sometimes|string|max:20|nullable',
                'address'               => 'sometimes|string|max:255|nullable',
                'city'                  => 'sometimes|string|max:100|nullable',
                'district'              => 'sometimes|string|max:100|nullable',
                'state'                 => 'sometimes|string|max:100|nullable',
                'pin_code'              => 'sometimes|string|max:10|nullable',
                'website_url'           => 'sometimes|url|max:255|nullable',
                'description'           => 'sometimes|string|max:500|nullable',
                'director_info'                     => 'sometimes|array',
                'director_info.*.director_name'     => 'sometimes|string|max:255',
                'director_info.*.director_pan_no'   => 'sometimes|string|max:20',
                'director_info.*.director_aadhar_no'=> 'sometimes|string|max:20',
                'director_info.*.director_gender'   => 'sometimes|string|in:male,female,other',
                'director_info.*.director_dob'      => 'sometimes|date',
                'director_info.*.user_pan_doc'      => 'sometimes|file|nullable',
                'director_info.*.user_addhar_doc'   => 'sometimes|file|nullable',
            ]);
    
            // Find merchant
            $merchant = User::where('role_id', 2)
                            ->where('id', $validatedData['id'])
                            ->first();
    
            if (!$merchant) {
                return response()->json([
                    'status' => false,
                    'message' => 'Merchant not found',
                ], 404);
            }
    
            // Handle password
            if (!empty($validatedData['password'])) {
                $validatedData['password'] = bcrypt($validatedData['password']);
            } else {
                unset($validatedData['password']);
            }
    
            // Handle file uploads for merchant
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
                    unset($validatedData[$field]);
                }
            }
    
            // Handle director info
            if ($request->has('director_info')) {
                $directors = [];
    
                foreach ($request->director_info as $index => $dir) {
                    $director = $dir;
    
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
    
                $validatedData['director_info'] = $directors;
            }
    
            // Update merchant
            $merchant->update($validatedData);
    
            return response()->json([
                'status'    => true,
                'message'   => 'Merchant updated successfully',
                'merchant'  => $merchant,
            ]);
    
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error_code' => 422,
                'message'    => 'Validation failed',
                'errors'     => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteMerchant($id)
    {
        // dd("deleteMerchant");
        try {
            // Find merchant with role_type = 'user'
            $merchant = User::where('role_id', 2) // example merchant role_id
                            ->where('id', $id)
                            ->first();
    
            // Handle if not found
            if (!$merchant) {
                return response()->json([
                    'status' => false,
                    'message' => 'Merchant not found',
                ], 404);
            }
    
            // Delete merchant
            $merchant->delete();
    
            return response()->json([
                'status' => true,
                'message' => 'Merchant deleted successfully',
            ], 200);
    
        } catch (\Exception $e) {
            // Catch any unexpected errors
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong: ' . $e->getMessage(),
            ], 500);
        }
}

    public function updateUserStatuses(Request $request)
    {
        // 1️⃣ Validate request data
        $validated = $request->validate([
            'user_id'        => 'required|exists:users,id',
            'account_status' => 'nullable|boolean',
        ], [
            'user_id.required' => 'User ID is required.',
            'user_id.exists'   => 'Invalid user ID.',
        ]);

        // 2️⃣ Find user
        $user = User::find($validated['user_id']);

        // 3️⃣ Update statuses (only provided fields)
        if ($request->has('account_status')) {
            $user->account_status = $validated['account_status'];
        }

        $user->save();

        // 4️⃣ Return JSON response
        return response()->json([
            'message' => 'User statuses updated successfully.',
            'data'    => [
                'user_id'        => $user->id,
                'account_status' => $user->account_status,
            ]
        ]);
    }
    
    public function addMoneyToMerchantWallet(Request $request)
    {
        try {
            // Validate input
            $validatedData = $request->validate([
                'merchant_id' => 'required|integer|exists:users,id',
                'amount'      => 'required|numeric|min:1',
                'product_type' => 'required|in:load_wallet',
                'remark'        => 'required|string|max:500'
            ]);
    
            $merchantId = $validatedData['merchant_id'];
            $amount     = $validatedData['amount'];
    
            // Fetch merchant with role_id = 2
            $merchant = User::where('role_id', 2)
                            ->where('id', $merchantId)
                            ->first();
    
            if (!$merchant) {
                return response()->json([
                    'status' => false,
                    'message' => 'Merchant not found or not authorized as merchant',
                ], 404);
            }
    
            // -------------------------
            // Capture Opening Balance BEFORE update
            // -------------------------
            $openingBalance = $merchant->merchant_bbps_wallet ?? 0;
    
            // -------------------------
            // Add money to wallet
            // -------------------------
            $merchant->merchant_bbps_wallet = $openingBalance + $amount;
            $merchant->save();
    
            // -------------------------
            // Capture Closing Balance AFTER update
            // -------------------------
            $closingBalance = $merchant->merchant_bbps_wallet;
    
            // Generate SPAY Transaction ID
            $spayTxnId = CommonSecurityService::spayTransactionId();
    
            // Create Report Entry
            Report::create([
                'user_id'                   => auth()->id(),
                'mobile'                    => $merchant->mobile_no,
                'amount'                    => $amount,
                'charge'                    => 0,
                'profit'                    => 0,
                'gst'                       => 0,
                'tds'                       => 0,
                'spay_txn_id'               => $spayTxnId,
                'description'               => $amount . ' amount loaded in merchant wallet',
                'payment_platform'          => 'agt_portal',
                'payout_amount'             => $amount,
                'payout_opening_balance'    => $openingBalance,
                'payout_closing_balance'    => $closingBalance,
                'payment_mode'              => 'cash',
                'payment_channel'           => 'agt',
                'transtion_type'            => 'debit',
                'product_type'              => $validatedData['product_type'],
                'remark'              => $validatedData['remark'],
                'commission_inc_gst'        => 0,
            ]);
    
            return response()->json([
                'status'  => true,
                'message' => 'Money added to merchant wallet successfully',
                'data'    => [
                    'merchant_id' => $merchant->id,
                    'added_amount' => $amount,
                    'opening_balance' => $openingBalance,
                    'closing_balance' => $closingBalance,
                    'spay_txn_id' => $spayTxnId,
                ]
            ]);
    
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
        }
}

    public function getUserSuccessfulReports($id)
    {
        // $data = DB::table('reports as r')
        //     ->join('users as u', 'u.id', '=', 'r.user_id')
        //     ->where('r.status', 'success')
        //     ->where('r.user_id', $id)
        //     ->select(
        //         'r.*',
        //         'u.name as user_name',
        //         'u.email as user_email'
        //     )
        //     ->get();
        
        $data = DB::table('reports as r')
    ->join('users as u', 'u.id', '=', 'r.user_id')
    ->join('bp_bill_payments_prod as bp', 'bp.request_id', '=', 'r.request_id')
    ->join('bharat_connect_mdm as bcm', 'bcm.blr_id', '=', 'bp.blr_id')
    ->where('r.status', 'success')
    ->where('r.user_id', $id)
    ->select(
        'r.*',
        'u.name as user_name',
        'u.email as user_email',
        'bcm.blr_category_name as biller_category'
    )
    ->get();


        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

}
