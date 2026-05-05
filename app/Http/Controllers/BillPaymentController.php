<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CommonSecurityService;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use App\Models\BillerInformation;
use App\Models\BharatConnectMdmTest;
use App\Models\BharatConnectMdm;
use App\Models\Scheme;
use App\Models\BfBillFetch;
use App\Models\BfBillFetchProd;
use App\Models\BpBillPayment;
use App\Models\BpBillPaymentProd;
use App\Models\BillFetch;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SimpleXMLElement;

class BillPaymentController extends Controller
{
 // ---------------------STAGED UAT--------------------------------

    //testing for amountInfo payload - rupee to paise conversion pending
    // public function billPayment(Request $request){
    //     try {
    //         $credentials  = CommonSecurityService::commonCredentials();
    //         $requestId    = CommonSecurityService::generateRequestId();
    //         $paymentRefId = CommonSecurityService::generatePaymentRefId();
    
    //         // ✅ Base validation for customer fields
    //         $baseRules = [
    //             'billerId'       => 'required|string|size:14',
    //             'remitterName'   => 'required|string|max:255',
    //             'customerMobile' => 'required|digits:10',
    //             'customerEmail'  => 'nullable|email',
    //             'customerAdhaar' => 'nullable|digits:12',
    //             'customerPan'    => 'nullable|string|max:10',
    //             'paymentMode'    => 'required|string|max:255',
    //             'quickPay'       => 'required|string|max:255',
    //             'splitPay'       => 'required|string|max:255',
    //         ];
    //         $validatedData = $request->validate($baseRules);
    
    //         // ✅ Fetch latest biller info with relationship
    //         $biller = BfBillFetch::with('bharatConnect')
    //             ->where('blr_id', $validatedData['billerId'])
    //             ->latest()
    //             ->first();
    
    //         if (!$biller) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Invalid billerId',
    //             ], 404);
    //         }
    
    //         // ✅ Extract biller info
    //         $mdmBillerResponse = $biller->bharatConnect->biller_response ?? [];
    //         $billerAdhoc = filter_var($mdmBillerResponse['billerAdhoc'] ?? false, FILTER_VALIDATE_BOOLEAN);
    //         $billerFetchRequirement = strtoupper($mdmBillerResponse['billerFetchRequiremet'] ?? 'MANDATORY');
    //         $billerPaymentExactness = strtoupper($mdmBillerResponse['billerPaymentExactness'] ?? '');
    
    //         $billerPaymentModes = $mdmBillerResponse['billerPaymentModes']['paymentModeList'] ?? [];
    //         $billerPaymentChannels = $mdmBillerResponse['billerPaymentChannels'][0]['paymentChannelList'] ?? [];
    
    //         $paymentChannel = $credentials['payment_channel']; // e.g., 'AGT'
    //         $userPaymentMode = strtoupper($validatedData['paymentMode']);
    
    //         // ✅ Validate payment channel
    //         $supportedChannels = collect($billerPaymentChannels)->pluck('paymentChannelName')->toArray();
    //         if (!in_array($paymentChannel, $supportedChannels)) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => "The selected biller does not support the payment channel '{$paymentChannel}'.",
    //             ], 400);
    //         }
    
    //         // ✅ Validate payment mode
    //         $supportedModes = collect($billerPaymentModes)->pluck('paymentModeName')->map(fn($m) => strtoupper($m))->toArray();
    //         if (!in_array($userPaymentMode, $supportedModes)) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => "Unsupported payment mode '{$userPaymentMode}'. Supported: " . implode(', ', $supportedModes),
    //             ], 400);
    //         }
    
    //         // ✅ Split pay validation
    //         $splitPay = strtoupper($validatedData['splitPay']);
    //         if ($credentials['payment_channel'] === 'AGT' && $splitPay === 'Y') {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => "Split payments are not supported for the AGT channel.",
    //             ], 400);
    //         }
    
    //         // ✅ Determine quickPay logic
    //         $hasBillFetchRecord = $biller->exists();
    //         switch ($billerFetchRequirement) {
    //             case 'MANDATORY':
    //                 $quickPay = 'N';
    //                 break;
    //             case 'OPTIONAL':
    //                 $userQuickPay = strtoupper($validatedData['quickPay']);
    //                 $quickPay = $userQuickPay === 'Y' ? 'Y' : ($hasBillFetchRecord ? 'N' : 'Y');
    //                 break;
    //             case 'NOT_SUPPORTED':
    //                 $quickPay = 'Y';
    //                 break;
    //             default:
    //                 $quickPay = 'N';
    //                 break;
    //         }
    
    //         // ✅ Prepare biller data references
    //         $inputParams = $biller->input_params['inputParams'] ?? ($biller->input_params ?? []);
    //         $billerResponse = $biller->biller_response['billerResponse'] ?? ($biller->biller_response ?? []);
    //         $additionalInfo = $biller->additional_info['additionalInfo'] ?? ($biller->additional_info ?? []);
    
    //         // ✅ Build payment method
    //         $paymentMethod = [
    //             "paymentMode" => ucfirst(strtolower($userPaymentMode)),
    //             "quickPay"    => $quickPay,
    //             "splitPay"    => $splitPay,
    //         ];
    
    //         $paymentInfo = [];
    //         if ($request->filled('remarks')) {
    //             $paymentInfo = [
    //                 "info" => [
    //                     [
    //                         "infoName"  => "Remarks",
    //                         "infoValue" => $request->input('remarks'),
    //                     ],
    //                 ],
    //             ];
    //         }
    
    //         /**
    //          * -------------------------------------------
    //          * 💰 amountInfo Section (core logic)
    //          * -------------------------------------------
    //          */
    //         $paymentModes = collect($billerPaymentModes);
    //         $currentMode = $paymentModes->firstWhere('paymentModeName', strtoupper($userPaymentMode));
    //         $minAmount = floatval($currentMode['minAmount'] ?? 1);
    //         $maxAmount = floatval($currentMode['maxAmount'] ?? 99999999);
    
    //         $userAmount = $request->input('amount');
    //         $billAmount = isset($billerResponse['billAmount']) ? floatval($billerResponse['billAmount']) : null;
    
    //         if ($billerAdhoc || $billerFetchRequirement === 'NOT_SUPPORTED') {
    //             // Case 1 & 2: Adhoc or Fetch Not Supported
    //             $request->validate([
    //                 'amount' => "required|numeric|min:$minAmount|max:$maxAmount",
    //             ]);
    //             $amountValue = floatval($userAmount);
    //             $exactness = "Adhoc";
    //         } elseif ($billerFetchRequirement === 'MANDATORY') {
    //             // Case 3: MANDATORY Fetch
    //             if ($quickPay === 'Y') {
    //                 return response()->json([
    //                     'status' => false,
    //                     'message' => "QuickPay not allowed for billers with MANDATORY fetch requirement.",
    //                 ], 400);
    //             }
    
    //             if (is_null($billAmount)) {
    //                 return response()->json([
    //                     'status' => false,
    //                     'message' => "Bill amount missing in fetched response.",
    //                 ], 400);
    //             }
    
    //             switch (strtoupper($billerPaymentExactness)) {
    //                 case 'EXACT':
    //                     $amountValue = $billAmount;
    //                     $exactness = "Exact";
    //                     break;
                
    //                 case 'EXACT AND ABOVE':
    //                     // only require manual amount if adhoc
    //                     if ($billerAdhoc === 'true') {
    //                         $request->validate([
    //                             'amount' => "required|numeric|min:$billAmount|max:$maxAmount",
    //                         ]);
    //                         $amountValue = floatval($userAmount);
    //                     } else {
    //                         // bill fetched, no manual input required
    //                         $amountValue = $billAmount;
    //                     }
    //                     $exactness = "Exact and above";
    //                     break;
                
    //                 case 'EXACT AND BELOW':
    //                     if ($billerAdhoc === 'true') {
    //                         $request->validate([
    //                             'amount' => "required|numeric|min:$minAmount|max:$billAmount",
    //                         ]);
    //                         $amountValue = floatval($userAmount);
    //                     } else {
    //                         $amountValue = $billAmount;
    //                     }
    //                     $exactness = "Exact and below";
    //                     break;
                
    //                 default:
    //                     $amountValue = $billAmount;
    //                     $exactness = "Exact";
    //                     break;
    //             }
    //         } else {
    //             // Case 4: OPTIONAL Fetch
    //             if ($quickPay === 'Y') {
    //                 $request->validate([
    //                     'amount' => "required|numeric|min:$minAmount|max:$maxAmount",
    //                 ]);
    //                 $amountValue = floatval($userAmount);
    //                 $exactness = "Any";
    //             } else {
    //                 if (is_null($billAmount)) {
    //                     return response()->json([
    //                         'status' => false,
    //                         'message' => "Bill amount missing for non-adhoc biller.",
    //                     ], 400);
    //                 }
    //                 $amountValue = $billAmount;
    //                 $exactness = $billerPaymentExactness ?: "Exact";
    //             }
    //         }
    
    //         // ✅ Calculate CCF1 (if applicable)
    //         $interchangeFee = $mdmBillerResponse['interchangeFeeCCF1'] ?? [];
    //         $flatFee = floatval($interchangeFee['flatFee'] ?? 0);
    //         $percentFee = floatval($interchangeFee['percentFee'] ?? 0);
    //         $ccf1 = 0;
    
    //         if (!empty($interchangeFee)) {
    //             $ccf1 = floor((($amountValue * $percentFee) / 100) + $flatFee);
    //             $gst = floor($ccf1 * 0.18);
    //             $ccf1 += $gst; // add GST to CCF1
    //         }
    
    //         // ✅ Final amountInfo block
    //         $amountInfo = [
    //             "amount"       => $amountValue,
    //             "currency"     => "356",
    //             "custConvFee"  => $ccf1,   // sent as CCF1+GST in request
    //             "ccf1"         => $ccf1,
    //             // "exactness"    => $exactness,
    //         ];
    
    //         /**
    //          * -------------------------------------------
    //          * 🧾 Final Payload
    //          * -------------------------------------------
    //          */
    //         $payload = [
    //             "agentId"        => $credentials['agent_id'],
    //             "billerAdhoc"    => $billerAdhoc,
    //             "agentDeviceInfo"=> [
    //                 "ip" => $request->ip(),
    //                 "initChannel" => $credentials['payment_channel'],
    //                 "mac" => 'A1-B2-C3-D4-E5-F6',
    //             ],
    //             "customerInfo"   => [
    //                 "REMITTER_NAME"   => $validatedData['remitterName'],
    //                 "customerMobile" => $validatedData['customerMobile'],
    //                 "customerEmail"  => $validatedData['customerEmail'] ?? '',
    //                 "customerAdhaar" => $validatedData['customerAdhaar'] ?? '',
    //                 "customerPan"    => $validatedData['customerPan'] ?? '',
    //             ],
    //             "billerId"       => $validatedData['billerId'],
    //             "inputParams"    => $inputParams,
    //             "billerResponse" => $billerResponse,
    //             "additionalInfo" => $additionalInfo,
    //             "paymentRefId"   => $paymentRefId,
    //             "paymentMethod"  => $paymentMethod,
    //             "paymentInfo"    => $paymentInfo,
    //             "amountInfo"     => $amountInfo,
    //         ];
    
    //         $encryptedRequest = CommonSecurityService::encryptTest(
    //             json_encode($payload, JSON_UNESCAPED_SLASHES),
    //             $credentials['working_key']
    //         );
    
    //         $url =
    //             "https://stgapi.billavenue.com/billpay/extBillPayCntrl/billPayRequest/json" .
    //             "?accessCode={$credentials['access_code']}" .
    //             "&instituteId={$credentials['agent_institution_id']}" .
    //             "&requestId={$biller->request_id}" .
    //             // . "&requestId=A7X9QPLMN3WZ8R4K2TY5V6UJBHC52621430"
    //             "&ver={$credentials['version']}" .
    //             "&encRequest={$encryptedRequest}";
    
    //         $curl = curl_init();
    
    //         curl_setopt_array($curl, [
    //             CURLOPT_URL => $url,
    //             CURLOPT_RETURNTRANSFER => true,
    //             CURLOPT_ENCODING => '',
    //             CURLOPT_MAXREDIRS => 10,
    //             CURLOPT_TIMEOUT => 0,
    //             CURLOPT_FOLLOWLOCATION => true,
    //             CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    //             CURLOPT_CUSTOMREQUEST => 'POST',
    //         ]);
    
    //         $response = curl_exec($curl);
    
    //         $curlError = curl_error($curl);
    //         curl_close($curl);
    
    //         if ($curlError) {
    //             return response()->json(
    //                 [
    //                     'status' => 'failed',
    //                     'message' => 'cURL error: ' . $curlError,
    //                 ],
    //                 500
    //             );
    //         }
            
    //         if (CommonSecurityService::isHex($response)) {
    //             $decryptedResponse = CommonSecurityService::decryptTest($response, $credentials['working_key']);
    //         } else {
    //             $decryptedResponse = $response;
    //         }
            
    //         $decoded = json_decode($decryptedResponse, true);
    //         if (json_last_error() === JSON_ERROR_NONE) {
    //             $decryptedResponse = $decoded;
    //         }
            
    //         return response()->json([
    //             // 'status'  => true,
    //             // 'message' => 'Payment payload ready',
    //             'response'    => $decryptedResponse,
    //         ]);
    //     }
    //     catch (\Illuminate\Validation\ValidationException $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Validation failed',
    //             'errors' => $e->errors(),
    //         ], 422);
    //     }
    //     catch (\Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Something went wrong',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }
    
    // testing for inserting reports as per transaction - success/failed
    public function billPayment(Request $request)
    {
        try {
            $credentials = CommonSecurityService::commonCredentials1();
            // dump($credentials);
            $requestId = CommonSecurityService::generateRequestId();
            // dump($requestId);
            $paymentRefId = CommonSecurityService::generatePaymentRefId();
            // dump($paymentRefId);

            // ✅ Base validation for customer fields
            $baseRules = [
                'billerId'       => 'required|string|size:14',
                'remitterName'   => 'required|string|max:255',
                'customerMobile' => 'required|digits:10',
                'customerEmail'  => 'nullable|email',
                'customerAdhaar' => 'nullable|digits:12',
                'customerPan'    => 'nullable|string|max:10',
                'paymentMode'    => 'required|string|max:255',
                'quickPay'       => 'required|string|max:255',
                'splitPay'       => 'required|string|max:255',
            ];

            // dump($baseRules);
            $validatedData = $request->validate($baseRules);
            // dump($validatedData);

            // ✅ Fetch latest biller info with relationship
            $biller = BfBillFetch::with('bharatConnect')
                ->where('blr_id', $validatedData['billerId'])
                ->where('user_id', auth()->id())
                ->latest()
                ->first();
            // dump($biller);

            if (! $biller) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Invalid billerId',
                ], 404);
            }

            // ✅ Extract biller info
            $mdmBillerResponse = $biller->bharatConnect->biller_response ?? [];
            // dump($mdmBillerResponse);
            $billerAdhoc = filter_var($mdmBillerResponse['billerAdhoc'] ?? false, FILTER_VALIDATE_BOOLEAN);
            // dump($billerAdhoc);
            $billerFetchRequirement = strtoupper($mdmBillerResponse['billerFetchRequiremet'] ?? 'MANDATORY');
            // dump($billerFetchRequirement);
            $billerPaymentExactness = strtoupper($mdmBillerResponse['billerPaymentExactness'] ?? '');
            // dump($billerPaymentExactness);

            $billerPaymentModes = $mdmBillerResponse['billerPaymentModes']['paymentModeList'] ?? [];
            // dump($billerPaymentModes);
            $billerPaymentChannels = $mdmBillerResponse['billerPaymentChannels'][0]['paymentChannelList'] ?? [];
                                                               // dump($billerPaymentChannels);
            $paymentChannel = $credentials['payment_channel']; // e.g., 'AGT'
                                                               // dump($paymentChannel);
            $userPaymentMode = strtoupper($validatedData['paymentMode']);
            // dump($userPaymentMode);

            // ✅ Validate payment channel
            $supportedChannels = collect($billerPaymentChannels)->pluck('paymentChannelName')->toArray();
            if (! in_array($paymentChannel, $supportedChannels)) {
                return response()->json([
                    'status'  => false,
                    'message' => "The selected biller does not support the payment channel '{$paymentChannel}'.",
                ], 400);
            }
            // dump($supportedChannels);

            // ✅ Validate payment mode
            $supportedModes = collect($billerPaymentModes)->pluck('paymentModeName')->map(fn($m) => strtoupper($m))->toArray();
            if (! in_array($userPaymentMode, $supportedModes)) {
                return response()->json([
                    'status'  => false,
                    'message' => "Unsupported payment mode '{$userPaymentMode}'. Supported: " . implode(', ', $supportedModes),
                ], 400);
            }
            // dump($supportedModes);

            // ✅ Split pay validation
            $splitPay = strtoupper($validatedData['splitPay']);
            // dump($splitPay);
            if ($credentials['payment_channel'] === 'AGT' && $splitPay === 'Y') {
                return response()->json([
                    'status'  => false,
                    'message' => "Split payments are not supported for the AGT channel.",
                ], 400);
            }

            // ✅ Determine quickPay logic
            $hasBillFetchRecord = $biller->exists();
            // dump($hasBillFetchRecord);
            switch ($billerFetchRequirement) {
                case 'MANDATORY':
                    $quickPay = 'N';
                    break;
                case 'OPTIONAL':
                    $userQuickPay = strtoupper($validatedData['quickPay']);
                    $quickPay     = $userQuickPay === 'Y' ? 'Y' : ($hasBillFetchRecord ? 'N' : 'Y');
                    break;
                case 'NOT_SUPPORTED':
                    $quickPay = 'Y';
                    break;
                default:
                    $quickPay = 'N';
                    break;
            }

            // ✅ Prepare biller data references
            // $inputParams = $biller->input_params['inputParams'] ?? ($biller->input_params ?? []);
            $inputParams = empty($biller->input_params['inputParams']) ? [[]] : $biller->input_params['inputParams'];
            // dump($inputParams);
            // $billerResponse = $biller->biller_response['billerResponse'] ?? ($biller->biller_response ?? []);
            $billerResponse = empty($biller->biller_response['billerResponse']) ? [[]] : $biller->biller_response['billerResponse'];
            // dump($billerResponse);
            // $additionalInfo = $biller->additional_info['additionalInfo'] ?? ($biller->additional_info ?? []);
            $additionalInfo = empty($biller->additional_info['additionalInfo']) ? [[]] : $biller->additional_info['additionalInfo'];

            // ✅ Build payment method
            $paymentMethod = [
                "paymentMode" => ucfirst(strtolower($userPaymentMode)),
                "quickPay"    => $quickPay,
                "splitPay"    => $splitPay,
            ];
            // dump($paymentMethod);

            $paymentInfo = [];
            if ($request->filled('remarks')) {
                $paymentInfo = [
                    "info" => [
                        [
                            "infoName"  => "Remarks",
                            "infoValue" => $request->input('remarks'),
                        ],
                    ],
                ];
            }
            // dump($paymentInfo);

            /**
             * -------------------------------------------
             * 💰 Validate Amount Range by Payment Channel (Same as Payment Mode)
             * -------------------------------------------
             */
            $paymentChannels = collect($billerPaymentChannels);

            $currentChannel = $paymentChannels->firstWhere(
                'paymentChannelName',
                strtoupper($paymentChannel)
            );

            $minChannelAmount = floatval($currentChannel['minAmount'] ?? 1);
            $maxChannelAmount = floatval($currentChannel['maxAmount'] ?? 99999999);

            $userAmount = $request->input('amount');

            // Amount range validation for channel
            if ($userAmount !== null) {
                $request->validate([
                    'amount' => "numeric|min:$minChannelAmount|max:$maxChannelAmount",
                ]);
            }

            /**
             * -------------------------------------------
             * 💰 amountInfo Section (core logic)
             * -------------------------------------------
             */
            $paymentModes = collect($billerPaymentModes);
            // dump($paymentModes);
            $currentMode = $paymentModes->firstWhere('paymentModeName', strtoupper($userPaymentMode));
            // dump($currentMode);
            $minAmount = floatval($currentMode['minAmount'] ?? 1);
            // dump($minAmount);
            $maxAmount = floatval($currentMode['maxAmount'] ?? 99999999);
            // dump($maxAmount);
            $userAmount = $request->input('amount');
            // dump($userAmount);
            $billAmount = isset($billerResponse['billAmount']) ? floatval($billerResponse['billAmount']) : null;
            // dump($billAmount);

            if ($billerAdhoc || $billerFetchRequirement === 'NOT_SUPPORTED') {
                // Case 1 & 2: Adhoc or Fetch Not Supported
                $request->validate([
                    'amount' => "required|numeric|min:$minAmount|max:$maxAmount",
                ]);
                $amountValue = floatval($userAmount);
                $exactness   = "Adhoc";
            } elseif ($billerFetchRequirement === 'MANDATORY') {
                // Case 3: MANDATORY Fetch
                if ($quickPay === 'Y') {
                    return response()->json([
                        'status'  => false,
                        'message' => "QuickPay not allowed for billers with MANDATORY fetch requirement.",
                    ], 400);
                }

                if (is_null($billAmount)) {
                    return response()->json([
                        'status'  => false,
                        'message' => "Bill amount missing in fetched response.",
                    ], 400);
                }

                switch (strtoupper($billerPaymentExactness)) {
                    case 'EXACT':
                        $amountValue = $billAmount;
                        $exactness   = "Exact";
                        break;

                    case 'EXACT AND ABOVE':
                        // only require manual amount if adhoc
                        if ($billerAdhoc === 'true') {
                            $request->validate([
                                'amount' => "required|numeric|min:$billAmount|max:$maxAmount",
                            ]);
                            $amountValue = floatval($userAmount);
                        } else {
                            // bill fetched, no manual input required
                            $amountValue = $billAmount;
                        }
                        $exactness = "Exact and above";
                        break;

                    case 'EXACT AND BELOW':
                        if ($billerAdhoc === 'true') {
                            $request->validate([
                                'amount' => "required|numeric|min:$minAmount|max:$billAmount",
                            ]);
                            $amountValue = floatval($userAmount);
                        } else {
                            $amountValue = $billAmount;
                        }
                        $exactness = "Exact and below";
                        break;

                    default:
                        $amountValue = $billAmount;
                        $exactness   = "Exact";
                        break;
                }
            } else {
                // Case 4: OPTIONAL Fetch
                if ($quickPay === 'Y') {
                    $request->validate([
                        'amount' => "required|numeric|min:$minAmount|max:$maxAmount",
                    ]);
                    $amountValue = floatval($userAmount);
                    $exactness   = "Any";
                } else {
                    if (is_null($billAmount)) {
                        return response()->json([
                            'status'  => false,
                            'message' => "Bill amount missing for non-adhoc biller.",
                        ], 400);
                    }
                    $amountValue = $billAmount;
                    $exactness   = $billerPaymentExactness ?: "Exact";
                }
            }

            // ✅ Calculate CCF1 (if applicable)
            $interchangeFee = $mdmBillerResponse['interchangeFeeCCF1'] ?? [];
            // dump($interchangeFee);
            $flatFee = floatval($interchangeFee['flatFee'] ?? 0);
            // dump($flatFee);
            $percentFee = floatval($interchangeFee['percentFee'] ?? 0);
            // dump($percentFee);
            $ccf1 = 0;
            // dump($ccf1);
           $schemeAmount = Scheme::select('merchant_id', 'type', 'commission_value', 'gst_value')
                ->whereRaw('JSON_CONTAINS(merchant_id, "' . auth()->id() . '")')
                ->get();
            //   dd($schemeAmount->first()->commission_value);
            $custConvFee = $schemeAmount->first()->commission_value;

            if (! empty($interchangeFee)) {
                $ccf1 = floor((($amountValue * $percentFee) / 100) + $flatFee);
                $gst  = floor($ccf1 * 0.18);
                $ccf1 += $gst; // add GST to CCF1
            }

            // ✅ Final amountInfo block
            $amountInfo = [
                "amount"      => strval($amountValue), // Convert to string
                "currency"    => "356",
                // "custConvFee"  => strval($ccf1),             // Convert to string
                "custConvFee" => "0",           // Convert to string
                "CCF1"        => strval($ccf1), // Convert to string & uppercase key
                // "exactness"    => $exactness,
            ];
            // dump($amountInfo);

            /**
             * -------------------------------------------
             * 🧾 Final Payload
             * -------------------------------------------
             */
            $payload = [
                "agentId"         => $credentials['agent_id'],
                "billerAdhoc"     => $billerAdhoc,
                "agentDeviceInfo" => [
                    "ip"          => $request->ip(),
                    "initChannel" => $credentials['payment_channel'],
                    "mac"         => 'A1-B2-C3-D4-E5-F6',
                ],
                "customerInfo"    => [
                    "REMITTER_NAME"  => $validatedData['remitterName'],
                    "customerMobile" => $validatedData['customerMobile'],
                    "customerEmail"  => $validatedData['customerEmail'] ?? '',
                    "customerAdhaar" => $validatedData['customerAdhaar'] ?? '',
                    "customerPan"    => $validatedData['customerPan'] ?? '',
                ],
                "billerId"        => $validatedData['billerId'],
                "inputParams"     => $inputParams,
                "billerResponse"  => $billerResponse,
                "additionalInfo"  => $additionalInfo,
                "paymentRefId"    => $paymentRefId,
                "paymentMethod"   => $paymentMethod,
                "paymentInfo"     => $paymentInfo,
                "amountInfo"      => $amountInfo,
            ];
            // dump($payload);

            $encryptedRequest = CommonSecurityService::encryptTest(
                json_encode($payload, JSON_UNESCAPED_SLASHES),
                $credentials['working_key']
            );
            // dd($encryptedRequest);
            // return $payload;

            $amountValueInRuppees = $amountValue / 100;
            // dump($amountValueInRuppees);

            // --------------------------------------------
            // 💰 Deduct money from merchant wallet BEFORE API call
            // --------------------------------------------
            $merchant = User::where('role_id', 2)->where('id', auth()->id())->first();

            if (! $merchant) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Merchant not found',
                ], 404);
            }

            $openingBalance = $merchant->merchant_bbps_wallet ?? 0;

            if ($openingBalance < $amountValueInRuppees) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Insufficient wallet balance',
                ], 400);
            }
            // dump($amountValueInRuppees);
            // Deduct wallet
            $merchant->merchant_bbps_wallet = $openingBalance - $amountValueInRuppees;
            $merchant->save();

            $url =
            "https://stgapi.billavenue.com/billpay/extBillPayCntrl/billPayRequest/json" .
            "?accessCode={$credentials['access_code']}" .
            "&instituteId={$credentials['agent_institution_id']}" .
            "&requestId={$biller->request_id}" .
            // . "&requestId=A7X9QPLMN3WZ8R4K2TY5V6UJBHC52621430"
            "&ver={$credentials['version']}" .
                "&encRequest={$encryptedRequest}";

            Log::info('Generated URL for BillPay Request', ['url' => $url]);
            // dd($url);

            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => '',
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => 'POST',
            ]);

            $response = curl_exec($curl);

            $curlError = curl_error($curl);
            curl_close($curl);

            if ($curlError) {
                return response()->json(
                    [
                        'status'  => 'failed',
                        'message' => 'cURL error: ' . $curlError,
                    ],
                    500
                );
            }

            if (CommonSecurityService::isHex($response)) {
                $decryptedResponse = CommonSecurityService::decryptTest($response, $credentials['working_key']);
                Log::info('Payment Decrypted response:', ['decrypted' => $decryptedResponse]);
            } else {
                $decryptedResponse = $response;
                Log::info('Payment Decrypted response:', ['decrypted' => $decryptedResponse]);
            }

            $decoded = json_decode($decryptedResponse, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $decryptedResponse = $decoded;
                Log::info('Decoded JSON response:', ['decoded' => $decoded]);
            }

            // -------------------------------
            //  ✅ SAVE PAYMENT RESPONSE IN DB
            // -------------------------------
            // dd(auth()->id());
            BpBillPayment::create([
                'user_id'      => auth()->id(),
                'blr_id'       => $validatedData['billerId'],
                'request_id'   => $biller->request_id,
                'txnRefID'     => $decryptedResponse['txnRefId'] ?? null,
                'mobile_no'    => $validatedData['customerMobile'],
                'txnStatus'    => $decryptedResponse['responseCode'] ?? null,
                'pay_response' => $decryptedResponse ?? null,
            ]);

            // --------------------------------------------
            // 🧾 Transaction success or rollback
            // --------------------------------------------
            $responseCode   = $decryptedResponse['responseCode'] ?? null;
            $responseReason = $decryptedResponse['responseReason'] ?? null;
            $txnRefId       = $decryptedResponse['txnRefId'] ?? null;
            $txnRespType    = $decryptedResponse['txnRespType'] ?? null;

            $success = (
                $responseCode === "000" &&
                $responseReason === "Successful" &&
                strtoupper($txnRespType) === "FORWARD TYPE RESPONSE"
            );

            if (! $success) {
                // ❌ ROLLBACK: Add amount back
                $merchant->merchant_bbps_wallet = $merchant->merchant_bbps_wallet + $amountValueInRuppees;
                $merchant->save();
            }

            $closingBalance = $merchant->merchant_bbps_wallet;

            $response = $decryptedResponse ?? null;
            // dd($response);
            $errorCode    = $response['vErrorRootVO']['error'][0]['errorCode'] ?? null;
            $errorMessage = $response['vErrorRootVO']['error'][0]['errorMessage'] ?? null;

            // dd($errorMessage);
            $description = "Common error:(Details: $errorMessage)";

            if ($success) {
                $description = "Bill Payment Successful";
            } elseif ($responseCode === '200') {
                $description = "Bill Payment Failed (Details: $errorMessage  (Amount Reversed))";

            }

            // // --------------------------------------------
            // // 📝 Insert Report
            // // --------------------------------------------
            Report::create([
                'user_id'                => auth()->id(),
                'mobile'                 => $validatedData['customerMobile'],
                'amount'                 => $amountValueInRuppees,
                'charge'                 => 0,
                'profit'                 => 0,
                'gst'                    => 0,
                'tds'                    => 0,
                'spay_txn_id'            => CommonSecurityService::spayTransactionId(),
                'description'            => $description,
                'payment_platform'       => 'agt_portal',

                'payout_amount'          => $amountValueInRuppees,
                'payout_opening_balance' => $openingBalance,
                'payout_closing_balance' => $closingBalance,

                'payment_mode'           => $validatedData['paymentMode'],
                'payment_channel'        => 'agt',
                'transtion_type'         => $success ? 'debit' : 'credit',
                'status'                 => $success ? 'success' : 'failed',
                'product_type'           => 'merchant_transaction',
                'commission_inc_gst'     => 0,
            ]);

            return response()->json([
                // 'status'  => true,
                // 'message' => 'Payment payload ready',
                'response' => $decryptedResponse,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
    

    public function allPaymentdata(Request $request)
    {

        // Load all payment records
        $data = BpBillPayment::with('user')->get();

        $filteredData = $data->map(function ($item) {

            // Get category from BharatConnectMdmTest by matching blr_id
            $mdm      = BharatConnectMdmTest::where('blr_id', $item->blr_id)->first();
            $category = $mdm->blr_category_name ?? null;

            // Decode pay_response safely
            $payResponse = is_string($item->pay_response)
                ? (json_decode($item->pay_response, true)['response'] ?? [])
                : ($item->pay_response['response'] ?? []);

            return [
                'id'             => $item->id,
                'user_id'        => $item->user_id,
                 'u_name'      => $item->user->name ?? null, // ⭐ ADD USER NAME
                'blr_id'         => $item->blr_id,
                'request_id'     => $item->request_id,
                'txnRefID'       => $item->txnRefID,
                'mobile_no'      => $item->mobile_no,
                'txnStatus'      => $item->txnStatus,
                'created_at'     => $item->created_at,
                'updated_at'     => $item->updated_at,

                // New field from BharatConnectMdmTest
                'category'       => $category,

                // Pay response fields
                'responseReason' => $payResponse['responseReason'] ?? null,
                'respAmount'     => $payResponse['respAmount'] ?? null,
            ];
        });

        return response()->json($filteredData);
    }

    public function userPaymentdata(Request $request, $id)
    {
        // Load only the records for this user
        $data = BpBillPayment::where('user_id', $id)->get();
    
        $filteredData = $data->map(function ($item) {
    
            $mdm = BharatConnectMdmTest::where('blr_id', $item->blr_id)->first();
            $category = $mdm->blr_category_name ?? null;
    
            $payResponse = is_string($item->pay_response)
                ? (json_decode($item->pay_response, true)['response'] ?? [])
                : ($item->pay_response['response'] ?? []);
    
            return [
                'id'             => $item->id,
                'user_id'        => $item->user_id,
                'blr_id'         => $item->blr_id,
                'request_id'     => $item->request_id,
                'txnRefID'       => $item->txnRefID,
                'mobile_no'      => $item->mobile_no,
                'txnStatus'      => $item->txnStatus,
                'created_at'     => $item->created_at,
                'updated_at'     => $item->updated_at,
                'category'       => $category,
                'responseReason' => $payResponse['responseReason'] ?? null,
                'respAmount'     => $payResponse['respAmount'] ?? null,
            ];
        });
    
        return response()->json([
            "status" => true,
            "data"   => $filteredData
        ]);
    }









// ---------------------PRODUCTION--------------------------------

    //only creating report entry after payment response
    // public function billPaymentProd(Request $request)
    // {
    //     try {
    //         // dd("billPaymentProd");
    //         $credentials = CommonSecurityService::commonCredentials();
    //         // dump($credentials);
    //         $requestId = CommonSecurityService::generateRequestIdProd();
    //         // dump($requestId);
    //         $paymentRefId = CommonSecurityService::generatePaymentRefId();
    //         // dump($paymentRefId);

    //         // ✅ Base validation for customer fields
    //         $baseRules = [
    //             'billerId'       => 'required|string|size:14',
    //             'remitterName'   => 'required|string|max:255',
    //             'customerMobile' => 'required|digits:10',
    //             'customerEmail'  => 'nullable|email',
    //             'customerAdhaar' => 'nullable|digits:12',
    //             'customerPan'    => 'nullable|string|max:10',
    //             'paymentMode'    => 'required|string|max:255',
    //             'quickPay'       => 'required|string|max:255',
    //             'splitPay'       => 'required|string|max:255',
    //             'remarks'        => 'required|string|max:255',
    //         ];

    //         // dump($baseRules);
    //         $validatedData = $request->validate($baseRules);
    //         // dump($validatedData);

    //         // ✅ Fetch latest biller info with relationship
    //         $biller = BfBillFetchProd::with('bharatConnect')
    //             ->where('blr_id', $validatedData['billerId'])
    //             ->where('user_id', auth()->id())
    //             ->latest()
    //             ->first();
    //         // dump($biller);

    //         if (! $biller) {
    //             return response()->json([
    //                 'status'  => false,
    //                 'message' => 'Invalid billerId',
    //             ], 404);
    //         }

    //         // ✅ Extract biller info
    //         $mdmBillerResponse = $biller->bharatConnect->biller_response ?? [];
    //         // dump($mdmBillerResponse);
    //         $billerAdhoc = filter_var($mdmBillerResponse['billerAdhoc'] ?? false, FILTER_VALIDATE_BOOLEAN);
    //         // dump($billerAdhoc);
    //         $billerFetchRequirement = strtoupper($mdmBillerResponse['billerFetchRequiremet'] ?? 'MANDATORY');
    //         // dump($billerFetchRequirement);
    //         $billerPaymentExactness = strtoupper($mdmBillerResponse['billerPaymentExactness'] ?? '');
    //         // dump($billerPaymentExactness);

    //         $billerPaymentModes = $mdmBillerResponse['billerPaymentModes']['paymentModeList'] ?? [];
    //         // dump($billerPaymentModes);
    //         $billerPaymentChannels = $mdmBillerResponse['billerPaymentChannels'][0]['paymentChannelList'] ?? [];
    //         // dump($billerPaymentChannels);
    //         $paymentChannel = $credentials['payment_channel']; // e.g., 'AGT'
    //         // dump($paymentChannel);
    //         $userPaymentMode = strtoupper($validatedData['paymentMode']);
    //         // dump($userPaymentMode);

    //         // ✅ Validate payment channel
    //         $supportedChannels = collect($billerPaymentChannels)->pluck('paymentChannelName')->toArray();
    //         if (! in_array($paymentChannel, $supportedChannels)) {
    //             return response()->json([
    //                 'status'  => false,
    //                 'message' => "The selected biller does not support the payment channel '{$paymentChannel}'.",
    //             ], 400);
    //         }
    //         // dump($supportedChannels);

    //         // ✅ Validate payment mode
    //         $supportedModes = collect($billerPaymentModes)->pluck('paymentModeName')->map(fn($m) => strtoupper($m))->toArray();
    //         if (! in_array($userPaymentMode, $supportedModes)) {
    //             return response()->json([
    //                 'status'  => false,
    //                 'message' => "Unsupported payment mode '{$userPaymentMode}'. Supported: " . implode(', ', $supportedModes),
    //             ], 400);
    //         }
    //         // dump($supportedModes);

    //         // ✅ Split pay validation
    //         $splitPay = strtoupper($validatedData['splitPay']);
    //         // dump($splitPay);
    //         if ($credentials['payment_channel'] === 'AGT' && $splitPay === 'Y') {
    //             return response()->json([
    //                 'status'  => false,
    //                 'message' => "Split payments are not supported for the AGT channel.",
    //             ], 400);
    //         }

    //         // ✅ Determine quickPay logic
    //         $hasBillFetchRecord = $biller->exists();
    //         // dump($hasBillFetchRecord);
    //         switch ($billerFetchRequirement) {
    //             case 'MANDATORY':
    //                 $quickPay = 'N';
    //                 break;
    //             case 'OPTIONAL':
    //                 $userQuickPay = strtoupper($validatedData['quickPay']);
    //                 $quickPay     = $userQuickPay === 'Y' ? 'Y' : ($hasBillFetchRecord ? 'N' : 'Y');
    //                 break;
    //             case 'NOT_SUPPORTED':
    //                 $quickPay = 'Y';
    //                 break;
    //             default:
    //                 $quickPay = 'N';
    //                 break;
    //         }

    //         // ✅ Prepare biller data references
    //         // $inputParams = empty($biller->input_params['inputParams']) ? [[]] : $biller->input_params['inputParams'];
    //         $inputParams     = $biller->input_params['inputParams'] ?? [];
    //         $includeInputParams  = !empty($inputParams);

    //         // $billerResponse = empty($biller->biller_response['billerResponse']) ? [[]] : $biller->biller_response['billerResponse'];
    //         $billerResponse     = $biller->biller_response['billerResponse'] ?? [];
    //         $includeBillerResponse  = !empty($billerResponse);

    //         // $additionalInfo = empty($biller->additional_info['additionalInfo']) ? [[]] : $biller->additional_info['additionalInfo'];
    //         $additionalInfo = $biller->additional_info['additionalInfo'] ?? [];
    //         $includeAdditionalInfo = !empty($additionalInfo);

    //         // ✅ Build payment method
    //         $paymentMethod = [
    //             "paymentMode" => ucfirst(strtolower($userPaymentMode)),
    //             "quickPay"    => $quickPay,
    //             "splitPay"    => $splitPay,
    //         ];
    //         // dump($paymentMethod);

    //         $paymentInfo = [
    //             "info" => [
    //                 [
    //                     "infoName"  => "Remarks",
    //                     "infoValue" => $validatedData['remarks'],
    //                 ],
    //             ],
    //         ];
    //         // dd(json_encode($paymentInfo, JSON_PRETTY_PRINT));

    //         /**
    //          * -------------------------------------------
    //          * 💰 Validate Amount Range by Payment Channel (Same as Payment Mode)
    //          * -------------------------------------------
    //          */
    //         $paymentChannels = collect($billerPaymentChannels);

    //         $currentChannel = $paymentChannels->firstWhere(
    //             'paymentChannelName',
    //             strtoupper($paymentChannel)
    //         );

    //         $minChannelAmount = floatval($currentChannel['minAmount'] ?? 1);
    //         $maxChannelAmount = floatval($currentChannel['maxAmount'] ?? 99999999);

    //         $userAmount = $request->input('amount');

    //         // Amount range validation for channel
    //         if ($userAmount !== null) {
    //             $request->validate([
    //                 'amount' => "numeric|min:$minChannelAmount|max:$maxChannelAmount",
    //             ]);
    //         }

    //         /**
    //          * -------------------------------------------
    //          * 💰 amountInfo Section (core logic)
    //          * -------------------------------------------
    //          */
    //         $paymentModes = collect($billerPaymentModes);
    //         // dump($paymentModes);
    //         $currentMode = $paymentModes->firstWhere('paymentModeName', strtoupper($userPaymentMode));
    //         // dump($currentMode);
    //         $minAmount = floatval($currentMode['minAmount'] ?? 1);
    //         // dump($minAmount);
    //         $maxAmount = floatval($currentMode['maxAmount'] ?? 99999999);
    //         // dump($maxAmount);
    //         $userAmount = $request->input('amount');
    //         // dump($userAmount);
    //         $billAmount = isset($billerResponse['billAmount']) ? floatval($billerResponse['billAmount']) : null;
    //         // dump($billAmount);

    //         if ($billerAdhoc || $billerFetchRequirement === 'NOT_SUPPORTED') {
    //             // Case 1 & 2: Adhoc or Fetch Not Supported
    //             $request->validate([
    //                 'amount' => "required|numeric|min:$minAmount|max:$maxAmount",
    //             ]);
    //             $amountValue = floatval($userAmount);
    //             $exactness   = "Adhoc";
    //         } elseif ($billerFetchRequirement === 'MANDATORY') {
    //             // Case 3: MANDATORY Fetch
    //             if ($quickPay === 'Y') {
    //                 return response()->json([
    //                     'status'  => false,
    //                     'message' => "QuickPay not allowed for billers with MANDATORY fetch requirement.",
    //                 ], 400);
    //             }

    //             if (is_null($billAmount)) {
    //                 return response()->json([
    //                     'status'  => false,
    //                     'message' => "Bill amount missing in fetched response.",
    //                 ], 400);
    //             }

    //             switch (strtoupper($billerPaymentExactness)) {
    //                 case 'EXACT':
    //                     $amountValue = $billAmount;
    //                     $exactness   = "Exact";
    //                     break;

    //                 case 'EXACT AND ABOVE':
    //                     // only require manual amount if adhoc
    //                     if ($billerAdhoc === 'true') {
    //                         $request->validate([
    //                             'amount' => "required|numeric|min:$billAmount|max:$maxAmount",
    //                         ]);
    //                         $amountValue = floatval($userAmount);
    //                     } else {
    //                         // bill fetched, no manual input required
    //                         $amountValue = $billAmount;
    //                     }
    //                     $exactness = "Exact and above";
    //                     break;

    //                 case 'EXACT AND BELOW':
    //                     if ($billerAdhoc === 'true') {
    //                         $request->validate([
    //                             'amount' => "required|numeric|min:$minAmount|max:$billAmount",
    //                         ]);
    //                         $amountValue = floatval($userAmount);
    //                     } else {
    //                         $amountValue = $billAmount;
    //                     }
    //                     $exactness = "Exact and below";
    //                     break;

    //                 default:
    //                     $amountValue = $billAmount;
    //                     $exactness   = "Exact";
    //                     break;
    //             }
    //         } else {
    //             // Case 4: OPTIONAL Fetch
    //             if ($quickPay === 'Y') {
    //                 $request->validate([
    //                     'amount' => "required|numeric|min:$minAmount|max:$maxAmount",
    //                 ]);
    //                 $amountValue = floatval($userAmount);
    //                 $exactness   = "Any";
    //             } else {
    //                 if (is_null($billAmount)) {
    //                     return response()->json([
    //                         'status'  => false,
    //                         'message' => "Bill amount missing for non-adhoc biller.",
    //                     ], 400);
    //                 }
    //                 $amountValue = $billAmount;
    //                 $exactness   = $billerPaymentExactness ?: "Exact";
    //             }
    //         }

    //         // ✅ Calculate CCF1 (if applicable)
    //         $interchangeFee = $mdmBillerResponse['interchangeFeeCCF1'] ?? [];
    //         // dump($interchangeFee);
    //         $flatFee = floatval($interchangeFee['flatFee'] ?? 0);
    //         // dump($flatFee);
    //         $percentFee = floatval($interchangeFee['percentFee'] ?? 0);
    //         // dump($percentFee);
    //         $ccf1 = 0;
    //         $gst = 0;
    //         // dump($ccf1);

    //       $schemeAmount = Scheme::select('merchant_id', 'type', 'commission_value', 'gst_value')
    //             ->whereRaw('JSON_CONTAINS(merchant_id, "' . auth()->id() . '")')
    //             ->get();
    //         //   dd($schemeAmount->first()->commission_value);
    //         $custConvFee        = $schemeAmount->first()->commission_value;
    //         $custConvFeeRuppees = $custConvFee * 100;
    //         if (! empty($interchangeFee)) {
    //             $ccf1 = floor((($amountValue * $percentFee) / 100) + $flatFee);
    //             $gst  = floor($ccf1 * 0.18);
    //             $ccf1 += $gst; // add GST to CCF1
    //         }
    //         // dd($gst);
            
    //         $amountTags = null;

    //         $amountOptions = $billerResponse['amountOptions']['option'] ?? null;
    //         if ($amountOptions && is_array($amountOptions)) {
    //             foreach ($amountOptions as $option) {
    //                 // Match amountValue from billerResponse to your calculated $amountValue
    //                 if (intval($option['amountValue']) === intval($amountValue)) {
    //                     $amountTags = [
    //                         [
    //                             "amountTag" => $option['amountName'],  // e.g., "Plan 2 Amount"
    //                             "value"     => $option['amountValue'] // e.g., "4900"
    //                         ]
    //                     ];
    //                     break;
    //                 }
    //             }
    //         }

    //         // ✅ Final amountInfo block
    //         $amountInfo = [
    //             "amount"      => strval($amountValue), // Convert to string
    //             "currency"    => 356,
    //             // "custConvFee"  => 0,             // Convert to string
    //             "custConvFee" => $custConvFeeRuppees, // Convert to string
    //             "CCF1"        => $ccf1             // Convert to string & uppercase key
    //         ];
            
    //         // Add only when found
    //         if (!empty($amountTags)) {
    //             $amountInfo['amountTags'] = $amountTags;
    //         }

    //         // dd(json_encode($amountInfo, JSON_PRETTY_PRINT));

    //         /**
    //          * -------------------------------------------
    //          * 🧾 Final Payload
    //          * -------------------------------------------
    //          */
    //         $payload = [
    //             "agentId"         => $credentials['agent_id'],
    //             "billerAdhoc"     => $billerAdhoc,
    //             "agentDeviceInfo" => [
    //                 "ip"          => $request->ip(),
    //                 "initChannel" => $credentials['payment_channel'],
    //                 "mac"         => 'A1-B2-C3-D4-E5-F6',
    //             ],
    //             "customerInfo"    => [
    //                 "REMITTER_NAME"  => $validatedData['remitterName'],
    //                 "customerMobile" => $validatedData['customerMobile'],
    //                 "customerEmail"  => $validatedData['customerEmail'] ?? '',
    //                 "customerAdhaar" => $validatedData['customerAdhaar'] ?? '',
    //                 "customerPan"    => $validatedData['customerPan'] ?? '',
    //             ],
    //             "billerId"        => $validatedData['billerId'],
    //         ];
            
    //         // Insert inputParams ONLY when available
    //         if ($includeInputParams) {
    //             $payload["inputParams"] = $inputParams;
    //         }
            
    //         // Insert billerResponse ONLY when available
    //         if ($includeBillerResponse) {
    //             $payload["billerResponse"] = $billerResponse;
    //         }
            
    //         // Insert additionalInfo ONLY when available
    //         if ($includeAdditionalInfo) {
    //             $payload["additionalInfo"] = $additionalInfo;
    //         }
            
    //         // Continue mandatory fields after optional blocks
    //         $payload += [
    //             "paymentRefId"    => $paymentRefId,
    //             "paymentMethod"   => $paymentMethod,
    //             "paymentInfo"     => $paymentInfo,
    //             "amountInfo"      => $amountInfo,
    //         ];

    //         // dd(json_encode($payload, JSON_PRETTY_PRINT));

    //         // return $payload;
            
    //         $encryptedRequest = CommonSecurityService::encryptTest(
    //             json_encode($payload, JSON_UNESCAPED_SLASHES),
    //             $credentials['working_key']
    //         );
    //         // dd($encryptedRequest);

    //         $amountValueInRuppees = $amountValue / 100;

    //         // --------------------------------------------
    //         // 💰 Deduct money from merchant wallet BEFORE API call
    //         // --------------------------------------------
    //         $merchant = User::where('role_id', 2)->where('id', auth()->id())->first();

    //         if (! $merchant) {
    //             return response()->json([
    //                 'status'  => false,
    //                 'message' => 'Merchant not found',
    //             ], 404);
    //         }

    //         $openingBalance = $merchant->merchant_bbps_wallet ?? 0;

    //         if ($openingBalance < $amountValueInRuppees) {
    //             return response()->json([
    //                 'status'  => false,
    //                 'message' => 'Insufficient wallet balance',
    //             ], 400);
    //         }
    //         // dump($amountValueInRuppees);
    //         // Deduct wallet
    //         $merchant->merchant_bbps_wallet = $openingBalance - $amountValueInRuppees;
    //         $merchant->save();

    //         $url =
    //         "https://api.billavenue.com/billpay/extBillPayCntrl/billPayRequest/json" .
    //         "?accessCode={$credentials['access_code']}" .
    //         "&instituteId={$credentials['agent_institution_id']}" .
    //         "&requestId={$biller->request_id}" .
    //         // . "&requestId=A7X9QPLMN3WZ8R4K2TY5V6UJBHC52621430"
    //         "&ver={$credentials['version']}" .
    //             "&encRequest={$encryptedRequest}";

    //         Log::channel('bill_payment_prod')->info('Generated URL for Production BillPay Request: ', [
    //             'url' => $url,
    //             'request_id' => $requestId,
    //         ]);
    //         dd($url);

    //         $curl = curl_init();

    //         curl_setopt_array($curl, [
    //             CURLOPT_URL            => $url,
    //             CURLOPT_RETURNTRANSFER => true,
    //             CURLOPT_ENCODING       => '',
    //             CURLOPT_MAXREDIRS      => 10,
    //             CURLOPT_TIMEOUT        => 0,
    //             CURLOPT_FOLLOWLOCATION => true,
    //             CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
    //             CURLOPT_CUSTOMREQUEST  => 'POST',
    //         ]);

    //         $response = curl_exec($curl);

    //         $curlError = curl_error($curl);
    //         curl_close($curl);

    //         if ($curlError) {
    //             return response()->json(
    //                 [
    //                     'status'  => 'failed',
    //                     'message' => 'cURL error: ' . $curlError,
    //                 ],
    //                 500
    //             );
    //         }

    //         if (CommonSecurityService::isHex($response)) {
    //             $decryptedResponse = CommonSecurityService::decryptTest($response, $credentials['working_key']);
    //             Log::info('Production Payment Decrypted response:', ['decrypted' => $decryptedResponse]);
    //         } else {
    //             $decryptedResponse = $response;
    //             Log::info('Production Payment Decrypted response:', ['decrypted' => $decryptedResponse]);
    //         }

    //         $decoded = json_decode($decryptedResponse, true);
    //         if (json_last_error() === JSON_ERROR_NONE) {
    //             $decryptedResponse = $decoded;
    //             Log::info('Decoded JSON response:', ['decoded' => $decoded]);
    //         }

    //         // -------------------------------
    //         //  ✅ SAVE PAYMENT RESPONSE IN DB
    //         // -------------------------------
    //         BpBillPaymentProd::create([
    //             'user_id'      => auth()->id(),
    //             'blr_id'       => $validatedData['billerId'],
    //             'request_id'   => $biller->request_id,
    //             'txnRefID'     => $decryptedResponse['txnRefId'] ?? null,
    //             'mobile_no'    => $validatedData['customerMobile'],
    //             'txnStatus'    => $decryptedResponse['responseCode'] ?? null,
    //             'pay_response' => $decryptedResponse ?? null,
    //         ]);

    //         // --------------------------------------------
    //         // 🧾 Transaction success or rollback
    //         // --------------------------------------------
    //         $responseCode   = $decryptedResponse['responseCode'] ?? null;
    //         $responseReason = $decryptedResponse['responseReason'] ?? null;
    //         $txnRefId       = $decryptedResponse['txnRefId'] ?? null;
    //         $txnRespType    = $decryptedResponse['txnRespType'] ?? null;

    //         $success = (
    //             $responseCode === "000" &&
    //             $responseReason === "Successful" &&
    //             strtoupper($txnRespType) === "FORWARD TYPE RESPONSE"
    //         );

    //         if (! $success) {
    //             // ❌ ROLLBACK: Add amount back
    //             $merchant->merchant_bbps_wallet = $merchant->merchant_bbps_wallet + $amountValueInRuppees;
    //             $merchant->save();
    //         }

    //         $closingBalance = $merchant->merchant_bbps_wallet;

    //         $response = $decryptedResponse ?? null;
    //         // dd($response);
    //         $errorCode    = $response['vErrorRootVO']['error'][0]['errorCode'] ?? null;
    //         $errorMessage = $response['vErrorRootVO']['error'][0]['errorMessage'] ?? null;

    //         // dd($errorMessage);
    //         $description = "Common error:(Details: $errorMessage)";

    //         if ($success) {
    //             $description = "Bill Payment Successful";
    //         } elseif ($responseCode === '200') {
    //             $description = "Bill Payment Failed (Details: $errorMessage  (Amount Reversed))";

    //         }

    //         // // --------------------------------------------
    //         // // 📝 Insert Report
    //         // // --------------------------------------------
    //         Report::create([
    //             'user_id'                => auth()->id(),
    //             'mobile'                 => $validatedData['customerMobile'],
    //             'amount'                 => $amountValueInRuppees,
    //             'charge'                 => 0,
    //             'profit'                 => 0,
    //             'gst'                    => $gst,
    //             'tds'                    => 0,
    //             'spay_txn_id'            => CommonSecurityService::spayTransactionId(),
    //             'description'            => $description,
    //             'payment_platform'       => 'agt_portal',
    //             'request_id'             => $requestId,
    //             'payment_ref_id'         => $txnRefId,
    //             'payout_amount'          => $amountValueInRuppees,
    //             'payout_opening_balance' => $openingBalance,
    //             'payout_closing_balance' => $closingBalance,

    //             'payment_mode'           => $validatedData['paymentMode'],
    //             'payment_channel'        => 'agt',
    //             'transtion_type'         => $success ? 'debit' : 'credit',
    //             'status'                 => $success ? 'success' : 'failed',
    //             'product_type'           => 'merchant_transaction',
    //             'commission_inc_gst'     => 0,
    //         ]);
    //         return response()->json([
    //             // 'status'  => true,
    //             // 'message' => 'Payment payload ready',
    //             'response' => $decryptedResponse,
    //         ]);
    //     } catch (\Illuminate\Validation\ValidationException $e) {
    //         return response()->json([
    //             'status'  => false,
    //             'message' => 'Validation failed',
    //             'errors'  => $e->errors(),
    //         ], 422);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status'  => false,
    //             'message' => 'Something went wrong',
    //             'error'   => $e->getMessage(),
    //         ], 500);
    //     }
    // }
    
    //creating report entry 1st then further updating it to from initiated to success/failed
    public function billPaymentProd(Request $request)
    {
        try {
            // dd("billPaymentProd");
            $credentials = CommonSecurityService::commonCredentials();
            // dump($credentials);
            $requestId = CommonSecurityService::generateRequestIdProd();
            // dump($requestId);
            $paymentRefId = CommonSecurityService::generatePaymentRefId();
            // dump($paymentRefId);

            // ✅ Base validation for customer fields
            $baseRules = [
                'billerId'       => 'required|string|size:14',
                'remitterName'   => 'required|string|max:255',
                'customerMobile' => 'required|digits:10',
                'customerEmail'  => 'nullable|email',
                'customerAdhaar' => 'nullable|digits:12',
                'customerPan'    => 'nullable|string|max:10',
                'paymentMode'    => 'required|string|max:255',
                'quickPay'       => 'required|string|max:255',
                'splitPay'       => 'required|string|max:255',
                'remarks'        => 'required|string|max:255',
            ];

            // dump($baseRules);
            $validatedData = $request->validate($baseRules);
            // dump($validatedData);

            // ✅ Fetch latest biller info with relationship
            $biller = BfBillFetchProd::with('bharatConnect')
                ->where('blr_id', $validatedData['billerId'])
                ->where('user_id', auth()->id())
                ->latest()
                ->first();
            // dump($biller);

            if (! $biller) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Invalid billerId',
                ], 404);
            }

            // ✅ Extract biller info
            $mdmBillerResponse = $biller->bharatConnect->biller_response ?? [];
            // dump($mdmBillerResponse);
            $billerAdhoc = filter_var($mdmBillerResponse['billerAdhoc'] ?? false, FILTER_VALIDATE_BOOLEAN);
            // dump($billerAdhoc);
            $billerFetchRequirement = strtoupper($mdmBillerResponse['billerFetchRequiremet'] ?? 'MANDATORY');
            // dump($billerFetchRequirement);
            $billerPaymentExactness = strtoupper($mdmBillerResponse['billerPaymentExactness'] ?? '');
            // dump($billerPaymentExactness);

            $billerPaymentModes = $mdmBillerResponse['billerPaymentModes']['paymentModeList'] ?? [];
            // dump($billerPaymentModes);
            $billerPaymentChannels = $mdmBillerResponse['billerPaymentChannels'][0]['paymentChannelList'] ?? [];
            // dump($billerPaymentChannels);
            $paymentChannel = $credentials['payment_channel']; // e.g., 'AGT'
            // dump($paymentChannel);
            $userPaymentMode = strtoupper($validatedData['paymentMode']);
            // dump($userPaymentMode);

            // ✅ Validate payment channel
            $supportedChannels = collect($billerPaymentChannels)->pluck('paymentChannelName')->toArray();
            if (! in_array($paymentChannel, $supportedChannels)) {
                return response()->json([
                    'status'  => false,
                    'message' => "The selected biller does not support the payment channel '{$paymentChannel}'.",
                ], 400);
            }
            // dump($supportedChannels);

            // ✅ Validate payment mode
            $supportedModes = collect($billerPaymentModes)->pluck('paymentModeName')->map(fn($m) => strtoupper($m))->toArray();
            if (! in_array($userPaymentMode, $supportedModes)) {
                return response()->json([
                    'status'  => false,
                    'message' => "Unsupported payment mode '{$userPaymentMode}'. Supported: " . implode(', ', $supportedModes),
                ], 400);
            }
            // dump($supportedModes);

            // ✅ Split pay validation
            $splitPay = strtoupper($validatedData['splitPay']);
            // dump($splitPay);
            if ($credentials['payment_channel'] === 'AGT' && $splitPay === 'Y') {
                return response()->json([
                    'status'  => false,
                    'message' => "Split payments are not supported for the AGT channel.",
                ], 400);
            }

            // ✅ Determine quickPay logic
            $hasBillFetchRecord = $biller->exists();
            // dump($hasBillFetchRecord);
            switch ($billerFetchRequirement) {
                case 'MANDATORY':
                    $quickPay = 'N';
                    break;
                case 'OPTIONAL':
                    $userQuickPay = strtoupper($validatedData['quickPay']);
                    $quickPay     = $userQuickPay === 'Y' ? 'Y' : ($hasBillFetchRecord ? 'N' : 'Y');
                    break;
                case 'NOT_SUPPORTED':
                    $quickPay = 'Y';
                    break;
                default:
                    $quickPay = 'N';
                    break;
            }

            // ✅ Prepare biller data references
            // $inputParams = empty($biller->input_params['inputParams']) ? [[]] : $biller->input_params['inputParams'];
            $inputParams     = $biller->input_params['inputParams'] ?? [];
            $includeInputParams  = !empty($inputParams);

            // $billerResponse = empty($biller->biller_response['billerResponse']) ? [[]] : $biller->biller_response['billerResponse'];
            $billerResponse     = $biller->biller_response['billerResponse'] ?? [];
            $includeBillerResponse  = !empty($billerResponse);

            // $additionalInfo = empty($biller->additional_info['additionalInfo']) ? [[]] : $biller->additional_info['additionalInfo'];
            $additionalInfo = $biller->additional_info['additionalInfo'] ?? [];
            $includeAdditionalInfo = !empty($additionalInfo);

            // ✅ Build payment method
            $paymentMethod = [
                "paymentMode" => ucfirst(strtolower($userPaymentMode)),
                "quickPay"    => $quickPay,
                "splitPay"    => $splitPay,
            ];
            // dump($paymentMethod);

            $paymentInfo = [
                "info" => [
                    [
                        "infoName"  => "Remarks",
                        "infoValue" => $validatedData['remarks'],
                    ],
                ],
            ];
            // dd(json_encode($paymentInfo, JSON_PRETTY_PRINT));

            /**
             * -------------------------------------------
             * 💰 Validate Amount Range by Payment Channel (Same as Payment Mode)
             * -------------------------------------------
             */
            $paymentChannels = collect($billerPaymentChannels);

            $currentChannel = $paymentChannels->firstWhere(
                'paymentChannelName',
                strtoupper($paymentChannel)
            );

            $minChannelAmount = floatval($currentChannel['minAmount'] ?? 1);
            $maxChannelAmount = floatval($currentChannel['maxAmount'] ?? 99999999);

            $userAmount = $request->input('amount');

            // Amount range validation for channel
            if ($userAmount !== null) {
                $request->validate([
                    'amount' => "numeric|min:$minChannelAmount|max:$maxChannelAmount",
                ]);
            }

            /**
             * -------------------------------------------
             * 💰 amountInfo Section (core logic)
             * -------------------------------------------
             */
            $paymentModes = collect($billerPaymentModes);
            // dump($paymentModes);
            $currentMode = $paymentModes->firstWhere('paymentModeName', strtoupper($userPaymentMode));
            // dump($currentMode);
            $minAmount = floatval($currentMode['minAmount'] ?? 1);
            // dump($minAmount);
            $maxAmount = floatval($currentMode['maxAmount'] ?? 99999999);
            // dump($maxAmount);
            $userAmount = $request->input('amount');
            // dump($userAmount);
            $billAmount = isset($billerResponse['billAmount']) ? floatval($billerResponse['billAmount']) : null;
            // dump($billAmount);

            if ($billerAdhoc || $billerFetchRequirement === 'NOT_SUPPORTED') {
                // Case 1 & 2: Adhoc or Fetch Not Supported
                $request->validate([
                    'amount' => "required|numeric|min:$minAmount|max:$maxAmount",
                ]);
                $amountValue = floatval($userAmount);
                $exactness   = "Adhoc";
            } elseif ($billerFetchRequirement === 'MANDATORY') {
                // Case 3: MANDATORY Fetch
                if ($quickPay === 'Y') {
                    return response()->json([
                        'status'  => false,
                        'message' => "QuickPay not allowed for billers with MANDATORY fetch requirement.",
                    ], 400);
                }

                if (is_null($billAmount)) {
                    return response()->json([
                        'status'  => false,
                        'message' => "Bill amount missing in fetched response.",
                    ], 400);
                }

                switch (strtoupper($billerPaymentExactness)) {
                    case 'EXACT':
                        $amountValue = $billAmount;
                        $exactness   = "Exact";
                        break;

                    case 'EXACT AND ABOVE':
                        // only require manual amount if adhoc
                        if ($billerAdhoc === 'true') {
                            $request->validate([
                                'amount' => "required|numeric|min:$billAmount|max:$maxAmount",
                            ]);
                            $amountValue = floatval($userAmount);
                        } else {
                            // bill fetched, no manual input required
                            $amountValue = $billAmount;
                        }
                        $exactness = "Exact and above";
                        break;

                    case 'EXACT AND BELOW':
                        if ($billerAdhoc === 'true') {
                            $request->validate([
                                'amount' => "required|numeric|min:$minAmount|max:$billAmount",
                            ]);
                            $amountValue = floatval($userAmount);
                        } else {
                            $amountValue = $billAmount;
                        }
                        $exactness = "Exact and below";
                        break;

                    default:
                        $amountValue = $billAmount;
                        $exactness   = "Exact";
                        break;
                }
            } else {
                // Case 4: OPTIONAL Fetch
                if ($quickPay === 'Y') {
                    $request->validate([
                        'amount' => "required|numeric|min:$minAmount|max:$maxAmount",
                    ]);
                    $amountValue = floatval($userAmount);
                    $exactness   = "Any";
                } else {
                    if (is_null($billAmount)) {
                        return response()->json([
                            'status'  => false,
                            'message' => "Bill amount missing for non-adhoc biller.",
                        ], 400);
                    }
                    $amountValue = $billAmount;
                    $exactness   = $billerPaymentExactness ?: "Exact";
                }
            }

            // ✅ Calculate CCF1 (if applicable)
            $interchangeFee = $mdmBillerResponse['interchangeFeeCCF1'] ?? [];
            // dump($interchangeFee);
            $flatFee = floatval($interchangeFee['flatFee'] ?? 0);
            // dump($flatFee);
            $percentFee = floatval($interchangeFee['percentFee'] ?? 0);
            // dump($percentFee);
            $ccf1 = 0;
            $gst = 0;
            // dump($ccf1);

           $schemeAmount = Scheme::select('merchant_id', 'type', 'commission_value', 'gst_value')
                ->whereRaw('JSON_CONTAINS(merchant_id, "' . auth()->id() . '")')
                ->get();
            //   dd($schemeAmount->first()->commission_value);
            $custConvFee        = $schemeAmount->first()->commission_value;
            $custConvFeeRuppees = $custConvFee * 100;
            if (! empty($interchangeFee)) {
                $ccf1 = floor((($amountValue * $percentFee) / 100) + $flatFee);
                $gst  = floor($ccf1 * 0.18);
                $ccf1 += $gst; // add GST to CCF1
            }
            // dd($gst);
            
            $amountTags = null;

            $amountOptions = $billerResponse['amountOptions']['option'] ?? null;
            if ($amountOptions && is_array($amountOptions)) {
                foreach ($amountOptions as $option) {
                    // Match amountValue from billerResponse to your calculated $amountValue
                    if (intval($option['amountValue']) === intval($amountValue)) {
                        $amountTags = [
                            [
                                "amountTag" => $option['amountName'],  // e.g., "Plan 2 Amount"
                                "value"     => $option['amountValue'] // e.g., "4900"
                            ]
                        ];
                        break;
                    }
                }
            }

            // ✅ Final amountInfo block
            $amountInfo = [
                "amount"      => strval($amountValue), // Convert to string
                "currency"    => 356,
                // "custConvFee"  => 0,             // Convert to string
                "custConvFee" => $custConvFeeRuppees, // Convert to string
                "CCF1"        => $ccf1             // Convert to string & uppercase key
            ];
            
            // Add only when found
            if (!empty($amountTags)) {
                $amountInfo['amountTags'] = $amountTags;
            }

            // dd(json_encode($amountInfo, JSON_PRETTY_PRINT));

            /**
             * -------------------------------------------
             * 🧾 Final Payload
             * -------------------------------------------
             */
            $payload = [
                "agentId"         => $credentials['agent_id'],
                "billerAdhoc"     => $billerAdhoc,
                "agentDeviceInfo" => [
                    "ip"          => $request->ip(),
                    "initChannel" => $credentials['payment_channel'],
                    "mac"         => 'A1-B2-C3-D4-E5-F6',
                ],
                "customerInfo"    => [
                    "REMITTER_NAME"  => $validatedData['remitterName'],
                    "customerMobile" => $validatedData['customerMobile'],
                    "customerEmail"  => $validatedData['customerEmail'] ?? '',
                    "customerAdhaar" => $validatedData['customerAdhaar'] ?? '',
                    "customerPan"    => $validatedData['customerPan'] ?? '',
                ],
                "billerId"        => $validatedData['billerId'],
            ];
            
            // Insert inputParams ONLY when available
            if ($includeInputParams) {
                $payload["inputParams"] = $inputParams;
            }
            
            // Insert billerResponse ONLY when available
            if ($includeBillerResponse) {
                $payload["billerResponse"] = $billerResponse;
            }
            
            // Insert additionalInfo ONLY when available
            if ($includeAdditionalInfo) {
                $payload["additionalInfo"] = $additionalInfo;
            }
            
            // Continue mandatory fields after optional blocks
            $payload += [
                "paymentRefId"    => $paymentRefId,
                "paymentMethod"   => $paymentMethod,
                "paymentInfo"     => $paymentInfo,
                "amountInfo"      => $amountInfo,
            ];

            dd(json_encode($payload, JSON_PRETTY_PRINT));
            
            Log::channel('bill_payment_prod')->info('Generated payload for Production BillPay Request: ', [
                'payload' => $payload,
                'request_id' => $requestId,
            ]);
            // return $payload;
            
            $encryptedRequest = CommonSecurityService::encryptTest(
                json_encode($payload, JSON_UNESCAPED_SLASHES),
                $credentials['working_key']
            );
            // dd($encryptedRequest);

            $amountValueInRuppees = $amountValue / 100;

            // --------------------------------------------
            // 💰 Deduct money from merchant wallet BEFORE API call
            // --------------------------------------------
            $merchant = User::where('role_id', 2)->where('id', auth()->id())->first();

            if (! $merchant) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Merchant not found',
                ], 404);
            }

            $openingBalance = $merchant->merchant_bbps_wallet ?? 0;

            if ($openingBalance < $amountValueInRuppees) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Insufficient wallet balance',
                ], 400);
            }
            // dump($amountValueInRuppees);
            // Deduct wallet
            $merchant->merchant_bbps_wallet = $openingBalance - $amountValueInRuppees;
            $merchant->save();
            
            $report = Report::create([
                'user_id'                => auth()->id(),
                'mobile'                 => $validatedData['customerMobile'],
                'amount'                 => $amountValueInRuppees,
                'charge'                 => 0,
                'profit'                 => 0,
                'gst'                    => 0,
                'tds'                    => 0,
                'spay_txn_id'            => CommonSecurityService::spayTransactionId(),
                'description'            => 'Bill payment initiated',
                'payment_platform'       => 'agt_portal',
                'request_id'             => $requestId,
                'payment_ref_id'         => null,
                'payout_amount'          => $amountValueInRuppees,
                'payout_opening_balance' => $openingBalance,
                'payout_closing_balance' => $openingBalance,
                'payment_mode'           => $validatedData['paymentMode'],
                'payment_channel'        => 'agt',
                'transtion_type'         => 'debit',
                'status'                 => 'pending',
                'product_type'           => 'merchant_transaction',
                'commission_inc_gst'     => 0,
            ]);

            $url =
            "https://api.billavenue.com/billpay/extBillPayCntrl/billPayRequest/json" .
            "?accessCode={$credentials['access_code']}" .
            "&instituteId={$credentials['agent_institution_id']}" .
            "&requestId={$biller->request_id}" .
            "&ver={$credentials['version']}" .
                "&encRequest={$encryptedRequest}";

            Log::channel('bill_payment_prod')->info('Generated URL for Production BillPay Request: ', [
                'url' => $url,
                'request_id' => $requestId,
            ]);
            dd($url);

            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => '',
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => 'POST',
            ]);

            $response = curl_exec($curl);

            $curlError = curl_error($curl);
            curl_close($curl);

            if ($curlError) {
                return response()->json(
                    [
                        'status'  => 'failed',
                        'message' => 'cURL error: ' . $curlError,
                    ],
                    500
                );
            }

            if (CommonSecurityService::isHex($response)) {
                $decryptedResponse = CommonSecurityService::decryptTest($response, $credentials['working_key']);
                Log::info('Production Payment Decrypted response:', ['decrypted' => $decryptedResponse]);
            } else {
                $decryptedResponse = $response;
                Log::info('Production Payment Decrypted response:', ['decrypted' => $decryptedResponse]);
            }

            $decoded = json_decode($decryptedResponse, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $decryptedResponse = $decoded;
                Log::info('Decoded JSON response:', ['decoded' => $decoded]);
            }

            // -------------------------------
            //  ✅ SAVE PAYMENT RESPONSE IN DB
            // -------------------------------
            BpBillPaymentProd::create([
                'user_id'      => auth()->id(),
                'blr_id'       => $validatedData['billerId'],
                'request_id'   => $biller->request_id,
                'txnRefID'     => $decryptedResponse['txnRefId'] ?? null,
                'mobile_no'    => $validatedData['customerMobile'],
                'txnStatus'    => $decryptedResponse['responseCode'] ?? null,
                'pay_response' => $decryptedResponse ?? null,
            ]);

            // --------------------------------------------
            // 🧾 Transaction success or rollback
            // --------------------------------------------
            $responseCode   = $decryptedResponse['responseCode'] ?? null;
            $responseReason = $decryptedResponse['responseReason'] ?? null;
            $txnRefId       = $decryptedResponse['txnRefId'] ?? null;
            $txnRespType    = $decryptedResponse['txnRespType'] ?? null;

            $success = (
                $responseCode === "000" &&
                $responseReason === "Successful" &&
                strtoupper($txnRespType) === "FORWARD TYPE RESPONSE"
            );

            if (! $success) {
                // ❌ ROLLBACK: Add amount back
                $merchant->merchant_bbps_wallet = $merchant->merchant_bbps_wallet + $amountValueInRuppees;
                $merchant->save();
            }

            $closingBalance = $merchant->merchant_bbps_wallet;

            $response = $decryptedResponse ?? null;
            // dd($response);
            $errorCode    = $response['vErrorRootVO']['error'][0]['errorCode'] ?? null;
            $errorMessage = $response['vErrorRootVO']['error'][0]['errorMessage'] ?? null;

            // dd($errorMessage);
            $description = "Common error:(Details: $errorMessage)";

            if ($success) {
                $description = "Bill Payment Successful";
            } elseif ($responseCode === '200') {
                $description = "Bill Payment Failed (Details: $errorMessage  (Amount Reversed))";

            }

            // // --------------------------------------------
            // // 📝 Update Report
            // // --------------------------------------------
            $report->update([
                'gst'                    => $gst,
                'payment_ref_id'         => $txnRefId,
                'description'            => $description,
                'payout_closing_balance' => $closingBalance,
                'transtion_type'         => $success ? 'debit' : 'credit',
                'status'                 => $success ? 'success' : 'failed',
            ]);
            
            return response()->json([
                // 'status'  => true,
                // 'message' => 'Payment payload ready',
                'response' => $decryptedResponse,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
    
    public function allPaymentdataProd(Request $request)
    {

        // Load all payment records
        $data = BpBillPaymentProd::all();

        $filteredData = $data->map(function ($item) {

            // Get category from BharatConnectMdmTest by matching blr_id
            $mdm      = BharatConnectMdm::where('blr_id', $item->blr_id)->first();
            $category = $mdm->blr_category_name ?? null;

            // Decode pay_response safely
            $payResponse = is_string($item->pay_response)
                ? json_decode($item->pay_response, true)
                : $item->pay_response;

            return [
                'id'             => $item->id,
                'user_id'        => $item->user_id,
                'blr_id'         => $item->blr_id,
                'request_id'     => $item->request_id,
                'txnRefID'       => $item->txnRefID,
                'mobile_no'      => $item->mobile_no,
                'txnStatus'      => $item->txnStatus,
                'created_at'     => $item->created_at,
                'updated_at'     => $item->updated_at,

                // New field from BharatConnectMdmTest
                'category'       => $category,

                // Pay response fields
                'responseReason' => $payResponse['responseReason'] ?? null,
                'respAmount'     => $payResponse['respAmount'] ?? null,
            ];
        });
        return response()->json($filteredData);
    }

    public function userPaymentdataProd(Request $request, $id)
    {
        // Load only the records for this user
        $data = BpBillPaymentProd::where('user_id', $id)->get();
    
        $filteredData = $data->map(function ($item) {
    
            // Get category from BharatConnectMdm by matching blr_id
            $mdm = BharatConnectMdm::where('blr_id', $item->blr_id)->first();
            $category = $mdm->blr_category_name ?? null;
    
            // Decode pay_response safely
            // $payResponse = is_string($item->pay_response)
            //     ? (json_decode($item->pay_response, true)['response'] ?? [])
            //     : ($item->pay_response['response'] ?? []);
            $payResponse = is_string($item->pay_response)
                ? json_decode($item->pay_response, true)
                : $item->pay_response;
            
            $payResponse = is_array($payResponse) ? $payResponse : [];
            
            // dd($payResponse);
            
            return [
                'id'             => $item->id,
                'user_id'        => $item->user_id,
                'blr_id'         => $item->blr_id,
                'request_id'     => $item->request_id,
                'txnRefID'       => $item->txnRefID,
                'mobile_no'      => $item->mobile_no,
                'txnStatus'      => $item->txnStatus,
                'created_at'     => $item->created_at,
                'updated_at'     => $item->updated_at,
                'category'       => $category,
                'responseReason' => $payResponse['responseReason'] ?? null,
                'respAmount'     => $payResponse['respAmount'] ?? null,
            ];
        });
    
        return response()->json([
            "status" => true,
            "data"   => $filteredData
        ]);
    }





}
