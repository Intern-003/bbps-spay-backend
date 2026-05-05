<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BillerInfos;
use App\Models\BillFetches;
use App\Services\CommonSecurityService;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SimpleXMLElement;

class BbpsBillAvenueController extends Controller
{
    //multiple biller ids
    public function fetchBillerInfo(Request $request)
    {
        // dd($request->biller_id);
        // Accept raw POST body
        $rawContent = $request->biller_id;

        // Split multiple biller IDs (if newline-separated)
        $billerIds = preg_split('/\r\n|\r|\n/', $rawContent);

        $credentials = CommonSecurityService::commonCredentials();
        $requestId   = CommonSecurityService::generateRequestId();
        // $requestId   = 'X9P7WQ2LRA8M3TY6B5NDZ4JCE1H52422111'; // for testing specific requestId
        Log::info('fetchBillerInfo', [$requestId]);

        // Build XML with multiple biller IDs
        $xmlBody = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<billerInfoRequest>\n";
        foreach ($billerIds as $id) {
            $xmlBody .= "    <billerId>" . trim($id) . "</billerId>\n";
        }
        $xmlBody .= "</billerInfoRequest>";

        $encryptedRequest = CommonSecurityService::encryptTest($xmlBody, $credentials['working_key']);

        $url = "https://stgapi.billavenue.com/billpay/extMdmCntrl/mdmRequestNew/xml"
            . "?accessCode={$credentials['access_code']}"
            . "&instituteId={$credentials['agent_institution_id']}"
            . "&ver={$credentials['version']}"
            . "&requestId={$requestId}";

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $encryptedRequest,
            CURLOPT_HTTPHEADER => ['Content-Type: text/plain'],
        ));

        $response = curl_exec($curl);
        // dd($response);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($curlError) {
            Log::error('BBPS cURL Error', ['error' => $curlError]);
            return response()->json([
                'status'  => 'failed',
                'message' => 'cURL error: ' . $curlError,
            ], 500);
        }

        if (CommonSecurityService::isHex($response)) {
            $decryptedResponse = CommonSecurityService::decryptTest($response, $credentials['working_key']);
            // dd("if".$decryptedResponse);
        } else {
            $decryptedResponse = $response;
            // dd("else".$decryptedResponse);
        }


        return response($decryptedResponse, 200)
            ->header('Content-Type', 'application/xml');
    } 

    public function fetchBillDetailBackup(Request $request)
    {
        $credentials = $this->commonCredentials();
        $requestId   = $this->generateRequestId();
        // $requestId   = "A7X9QPLMN3WZ8R4K2TY5V6UJBHC52621430";
        // dd($requestId);

        Log::info('fetchBillDetails', [$requestId]);

        // Step 1: Base rules
        $baseRules = [
            'billerId'       => 'required|string|size:14', // exactly 14 chars
            'customerEmail'  => 'nullable|email',
            'customerMobile' => 'required|digits:10',
            'customerAdhaar' => 'nullable|digits:12',
            'customerPan'   => 'nullable|string|max:10',
        ];

        // Step 2: Get biller params from DB
        $billerParams = DB::table('biller_infos')
            ->where('biller_id', $request->billerId)
            ->get();

        // Step 3: Build dynamic rules
        $dynamicRules = [];
        foreach ($billerParams as $param) {
            $fieldName = Str::camel(strtolower(str_replace(' ', '_', $param->param_name)));
            $rules = [];

            $rules[] = $param->is_optional === 'false' ? 'required' : 'nullable';
            if ($param->data_type === 'NUMERIC') {
                $rules[] = 'digits_between:' . $param->min_length . ',' . $param->max_length;
            } else {
                $rules[] = 'string';
                if (!empty($param->min_length)) {
                    $rules[] = 'min:' . $param->min_length;
                }
                if (!empty($param->max_length)) {
                    $rules[] = 'max:' . $param->max_length;
                }
            }

            if (!empty($param->reg_ex)) {
                // escape forward slashes if any
                $regex = str_replace('/', '\/', $param->reg_ex);
                $rules[] = "regex:/$regex/";
            }

            $dynamicRules[$fieldName] = $rules;
        }

        // Step 4: Merge and validate
        $rules = array_merge($baseRules, $dynamicRules);

        try {
            $validatedData = Validator::make($request->all(), $rules)->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e; // rethrow so you still get normal 422 response
        }

        // Step 5: Build inputParams dynamically
        $inputParams = [];
        foreach ($billerParams as $param) {
            $fieldName = Str::camel(strtolower(str_replace(' ', '_', $param->param_name)));
            $inputParams[] = [
                "paramName"  => $param->param_name,
                "paramValue" => $validatedData[$fieldName] ?? '',
            ];
        }
        // dd($validatedData, $inputParams);

        // Step 6: Get IP/MAC
        $ipAddress = $request->ip();
        $macAddress = null;
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            @exec('getmac', $output);
            if (isset($output[0])) {
                $macAddress = strtok($output[0], ' ');
            }
        } else {
            @exec('ifconfig -a', $output);
            $outputStr = implode("\n", $output);
            if (preg_match('/..:..:..:..:..:../', $outputStr, $matches)) {
                $macAddress = $matches[0];
            }
        }
        if (empty($macAddress)) {
            $macAddress = 'A1-B2-C3-D4-E5-F6';
        }

        // Step 7: Final payload
        $payload = [
            "requestId"       => $requestId,
            "agentId"         => $credentials['agent_id'],
            "agentDeviceInfo" => [
                "ip"          => $ipAddress,
                "initChannel" => $credentials['payment_channel'],
                "mac"         => $macAddress,
            ],
            "customerInfo"    => [
                "customerMobile" => $validatedData['customerMobile'],
                "customerEmail"  => $validatedData['customerEmail'] ?? '',
                "customerAdhaar" => $validatedData['customerAdhaar'] ?? '',
                "customerPan"   => $validatedData['customerPan'] ?? '',
            ],
            "billerId"        => $validatedData['billerId'],
            "inputParams"     => [
                "input" => $inputParams,
            ],
        ];

        // Step 8: Convert payload to XML
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><billFetchRequest/>');

        // agentDeviceInfo
        $agentDeviceInfo = $xml->addChild('agentDeviceInfo');
        foreach ($payload['agentDeviceInfo'] as $key => $value) {
            $agentDeviceInfo->addChild($key, htmlspecialchars($value));
        }

        // agentId
        $xml->addChild('agentId', $payload['agentId']);

        // billerId
        $xml->addChild('billerId', $payload['billerId']);

        // customerInfo
        $customerInfo = $xml->addChild('customerInfo');
        foreach ($payload['customerInfo'] as $key => $value) {
            $customerInfo->addChild($key, htmlspecialchars($value));
        }

        // inputParams
        $inputParams = $xml->addChild('inputParams');
        foreach ($payload['inputParams']['input'] as $input) {
            $inputNode = $inputParams->addChild('input');
            $inputNode->addChild('paramName', htmlspecialchars($input['paramName']));
            $inputNode->addChild('paramValue', htmlspecialchars($input['paramValue']));
        }

        $xmlString = $xml->asXML();

        // Encrypt the XML
        $encryptedRequest = $this->encryptTest($xmlString, $credentials['working_key']);
        // dd($payload, $xmlString, $encryptedRequest);

        $url =  "https://stgapi.billavenue.com/billpay/extBillCntrl/billFetchRequest/xml"
            . "?accessCode={$credentials['access_code']}"
            . "&instituteId={$credentials['agent_institution_id']}"
            . "&requestId={$requestId}"
            . "&ver={$credentials['version']}"
            . "&encRequest={$encryptedRequest}";

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
        ));

        $response = curl_exec($curl);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($curlError) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'cURL error: ' . $curlError,
            ], 500);
        }

        if ($this->isHex($response)) {
            $decryptedResponse = $this->decryptTest($response, $credentials['working_key']);
        } else {
            $decryptedResponse = $response;
        }
        // echo $response;

        // test to save in db
        $xml = simplexml_load_string($decryptedResponse);
        $xmlArray = json_decode(json_encode((array) $xml), true);

        $billerResponse = $xmlArray['billerResponse'] ?? null;
        $additionalInfo = $xmlArray['additionalInfo'] ?? null;
        $inputParams = $xmlArray['inputParams'] ?? null;

        $billerInfo = BillerInfos::first();

        $savedData =  BillFetches::create([
            'request_id'      => $requestId,
            'biller_info_id'      =>  $billerInfo->id,
            'biller_response' => json_encode($billerResponse),
            'additional_info' => json_encode($additionalInfo),
            'input_params'   => json_encode($inputParams)
        ]);

        // dd($billerResponse, $additionalInfo, $savedData->toArray());

        // DB::table('biller_info')->delete();

        return response($decryptedResponse, 200)
            ->header('Content-Type', 'application/xml');
    }
    
    public function fetchBillDetails(Request $request)
    {
    // Static data (will be used instead of DB)
    $validatedData = [
        'billerId'       => $request->billerId ?? 'DUMMY0000DIG08',
        'customerMobile' => $request->customerMobile ?? '9284210056',
        'customerEmail'  => $request->customerEmail ?? 'khanamaanak1@gmail.com',
        'customerAdhaar' => $request->customerAdhaar ?? '548550008000',
        'customerPan'    => $request->customerPan ?? 'AAAPZ1234C',
        'customerId'     => $request->customerId ?? 'h696077',
    ];

    // Skip dynamic DB rules for now
    $inputParams = [
        ["paramName" => "customerId", "paramValue" => $validatedData['customerId']],
        ["paramName" => "customerMobile", "paramValue" => $validatedData['customerMobile']],
        ["paramName" => "customerEmail", "paramValue" => $validatedData['customerEmail']],
        ["paramName" => "customerAdhaar", "paramValue" => $validatedData['customerAdhaar']],
        ["paramName" => "customerPan", "paramValue" => $validatedData['customerPan']],
    ];

    $requestId = CommonSecurityService::generateRequestId();
    $credentials = CommonSecurityService::commonCredentials();

    $payload = [
        "requestId"       => $requestId,
        "agentId"         => $credentials['agent_id'],
        "agentDeviceInfo" => [
            "ip" => $request->ip(),
            "initChannel" => $credentials['payment_channel'],
            "mac" => 'A1-B2-C3-D4-E5-F6',
        ],
        "customerInfo" => [
            "customerMobile" => $validatedData['customerMobile'],
            "customerEmail" => $validatedData['customerEmail'],
            "customerAdhaar" => $validatedData['customerAdhaar'],
            "customerPan" => $validatedData['customerPan'],
        ],
        "billerId" => $validatedData['billerId'],
        "inputParams" => ["input" => $inputParams],
    ];

    // Convert to XML
    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><billFetchRequest/>');
    $xml->addChild('agentId', $payload['agentId']);
    $billerIdNode = $xml->addChild('billerId', $payload['billerId']);

    $customerInfo = $xml->addChild('customerInfo');
    foreach ($payload['customerInfo'] as $key => $value) {
        $customerInfo->addChild($key, htmlspecialchars($value));
    }

    $inputParamsNode = $xml->addChild('inputParams');
    foreach ($inputParams as $input) {
        $inputNode = $inputParamsNode->addChild('input');
        $inputNode->addChild('paramName', htmlspecialchars($input['paramName']));
        $inputNode->addChild('paramValue', htmlspecialchars($input['paramValue']));
    }

    $xmlString = $xml->asXML();
    $encryptedRequest = CommonSecurityService::encryptTest($xmlString, $credentials['working_key']);

    $url = "https://stgapi.billavenue.com/billpay/extBillCntrl/billFetchRequest/xml"
        . "?accessCode={$credentials['access_code']}"
        . "&instituteId={$credentials['agent_institution_id']}"
        . "&requestId={$requestId}"
        . "&ver={$credentials['version']}"
        . "&encRequest={$encryptedRequest}";

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
    ]);

    $response = curl_exec($curl);
    $curlError = curl_error($curl);
    curl_close($curl);

    if ($curlError) {
        return response()->json(['status' => 'failed', 'message' => $curlError], 500);
    }

    $decryptedResponse = CommonSecurityService::isHex($response)
        ? CommonSecurityService::decryptTest($response, $credentials['working_key'])
        : $response;
// dd($decryptedResponse);
    return response($decryptedResponse, 200)->header('Content-Type', 'application/xml');
}

    
    public function processBillPayment(Request $request)
    {
        $credentials = CommonSecurityService::commonCredentials();
        $requestId   = CommonSecurityService::generateRequestId();
        // Step 1: Base rules
        $baseRules = [
            'billerId'       => 'required|string|size:14',
            'REMITTER_NAME'  => 'required|string',
            'customerEmail'  => 'nullable|email',
            'customerMobile' => 'required|digits:10',
            'customerAdhaar' => 'nullable|digits:12',
            'customerPan'   => 'nullable|string|max:10',
            'paymentMode'    => 'required|string',
            'quickPay'       => 'required|string',
            'splitPay'        => 'required|string',
            'paymentInfo'    => 'required|array',
            'paymentInfo.*.infoName'  => 'required|string',
            'paymentInfo.*.infoValue' => 'required|string',
        ];

        Log::debug('Validation rules', ['rules' => $baseRules]);

        try {
            $validatedData = Validator::make($request->all(), $baseRules)->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        }

        $billFetch = BillFetches::latest()->first();

        // Step 5: Build inputParams dynamically
        $inputParams = [];
        $billerResponse = [];
        $additionalInfo = [];

        // fetch input params from bill_fetch table
        // if ($billFetch && $billFetch->input_params) {
        //     $params = json_decode($billFetch->input_params, true);
        //     if (isset($params['input']) && is_array($params['input'])) {
        //         foreach ($params['input'] as $param) {
        //             // Only add valid ones
        //             if (!empty($param['paramName']) && !empty($param['paramValue'])) {
        //                 $inputParams[] = [
        //                     'paramName'  => $param['paramName'],
        //                     'paramValue' => $param['paramValue'],
        //                 ];
        //             }
        //         }
        //     }
        // }
        if ($billFetch && $billFetch->input_params) {
            $params = json_decode($billFetch->input_params, true);

            if (isset($params['input'])) {
                // Ensure it's always an array of arrays
                $inputs = isset($params['input'][0])
                    ? $params['input'] // already array of arrays
                    : [$params['input']]; // wrap single object in array

                foreach ($inputs as $param) {
                    if (!empty($param['paramName']) && !empty($param['paramValue'])) {
                        $inputParams[] = [
                            'paramName'  => $param['paramName'],
                            'paramValue' => $param['paramValue'],
                        ];
                    }
                }
            }
        }


        // fetch biller response from bill_fetch table
        if ($billFetch && $billFetch->biller_response) {
            $billerResponse = json_decode($billFetch->biller_response, true);
        }

        // fetch additional info from bill_fetch table
        if ($billFetch && $billFetch->additional_info) {
            $additionalInfo = json_decode($billFetch->additional_info, true);
        }

        $billerInfo = BillerInfos::first();

        // Step 6: Get IP/MAC
        $ipAddress = $request->ip();
        $macAddress = null;
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            @exec('getmac', $output);
            if (isset($output[0])) {
                $macAddress = strtok($output[0], ' ');
            }
        } else {
            @exec('ifconfig -a', $output);
            $outputStr = implode("\n", $output);
            if (preg_match('/..:..:..:..:..:../', $outputStr, $matches)) {
                $macAddress = $matches[0];
            }
        }
        if (empty($macAddress)) {
            $macAddress = 'A1-B2-C3-D4-E5-F6';
        }

        $billAmount = (int) ($billerResponse['billAmount'] ?? 0);

        // Step 7: Final payload
        $payload = [
            "agentId"         => $credentials['agent_id'],
            "paymentRefId"  => CommonSecurityService::generatePaymentRefId(),
            "billerAdhoc"    => $billerInfo->biller_adhoc,
            "agentDeviceInfo" => [
                "ip"          => $ipAddress,
                "initChannel" => $credentials['payment_channel'],
                "mac"         => $macAddress,
            ],
            "customerInfo"    => [
                "REMITTER_NAME"  => $validatedData['REMITTER_NAME'],
                "customerMobile" => $validatedData['customerMobile'],
                "customerEmail"  => $validatedData['customerEmail'] ?? '',
                "customerAdhaar" => $validatedData['customerAdhaar'] ?? '',
                "customerPan"    => ($billAmount > 5000000 && $validatedData['paymentMode'] === 'Cash')
                    ? $validatedData['customerPan']
                    : '',
            ],
            "billerId"        => $validatedData['billerId'],
            "inputParams"     => [
                "input" => $inputParams,
            ],
            "billerResponse"  => $billerResponse,
            "additionalInfo"  => $additionalInfo,
            "amountInfo"     => [
                "amount" => $billerResponse['billAmount'] ?? '0',
                "currency" => '356',
                "custConvFee" => '0',
                // "amountTags" => '' ? null : null,
            ],
            "paymentMethod"  => [
                "paymentMode" => $validatedData['paymentMode'],
                "quickPay"    => $validatedData['quickPay'],
                "splitPay"    => $validatedData['splitPay'],
            ],
            "paymentInfo"    => $validatedData['paymentInfo'],
        ];

        // Step 8: Convert payload to XML
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><billPaymentRequest/>');

        // agentId
        $xml->addChild('agentId', $payload['agentId']);

        // paymentRefId
        $xml->addChild('paymentRefId', $payload['paymentRefId']);

        // billerAdhoc
        $xml->addChild('billerAdhoc', $payload['billerAdhoc']);

        // agentDeviceInfo
        $agentDeviceInfo = $xml->addChild('agentDeviceInfo');
        foreach ($payload['agentDeviceInfo'] as $key => $value) {
            $agentDeviceInfo->addChild($key, htmlspecialchars($value));
        }

        // customerInfo
        $customerInfo = $xml->addChild('customerInfo');
        foreach ($payload['customerInfo'] as $key => $value) {
            $customerInfo->addChild($key, htmlspecialchars($value));
        }

        // billerId
        $xml->addChild('billerId', $payload['billerId']);

        // inputParams
        $inputParams = $xml->addChild('inputParams');
        foreach ($payload['inputParams']['input'] as $input) {
            $inputNode = $inputParams->addChild('input');
            $inputNode->addChild('paramName', htmlspecialchars($input['paramName']));
            $inputNode->addChild('paramValue', htmlspecialchars($input['paramValue']));
        }

        // billerResponse
        if (!empty($payload['billerResponse'])) {
            $billerResponseNode = $xml->addChild('billerResponse');

            foreach ($payload['billerResponse'] as $key => $value) {
                if (is_array($value)) {
                    // Special case: amountOptions
                    if ($key === "amountOptions" && isset($value['option'])) {
                        $amountOptionsNode = $billerResponseNode->addChild('amountOptions');
                        foreach ($value['option'] as $option) {
                            $optionNode = $amountOptionsNode->addChild('option');
                            if (isset($option['amountName'])) {
                                $optionNode->addChild('amountName', htmlspecialchars($option['amountName']));
                            }
                            if (isset($option['amountValue'])) {
                                $optionNode->addChild('amountValue', htmlspecialchars($option['amountValue']));
                            }
                        }
                    }
                } else {
                    // Add direct fields like billAmount, billDate, etc.
                    if (!empty($value)) {
                        $billerResponseNode->addChild($key, htmlspecialchars($value));
                    }
                }
            }
        }

        // additionalInfo
        if (!empty($payload['additionalInfo']) && isset($payload['additionalInfo']['info'])) {
            $additionalInfoNode = $xml->addChild('additionalInfo');
            foreach ($payload['additionalInfo']['info'] as $info) {
                $infoNode = $additionalInfoNode->addChild('info');
                if (!empty($info['infoName'])) {
                    $infoNode->addChild('infoName', htmlspecialchars($info['infoName']));
                }
                if (!empty($info['infoValue'])) {
                    $infoNode->addChild('infoValue', htmlspecialchars($info['infoValue']));
                }
            }
        }

        //fetch amount from bill_fetch for amountInfo params
        if (!empty($payload['amountInfo'])) {
            $amountInfoNode = $xml->addChild('amountInfo');
            foreach ($payload['amountInfo'] as $key => $value) {
                $amountInfoNode->addChild($key, htmlspecialchars($value));
            }
        }

        // paymentMethod
        $paymentMethodNode = $xml->addChild('paymentMethod');
        foreach ($payload['paymentMethod'] as $key => $value) {
            $paymentMethodNode->addChild($key, htmlspecialchars($value));
        }

        // paymentInfo
        $paymentInfoNode = $xml->addChild('paymentInfo');
        foreach ($payload['paymentInfo'] as $info) {
            $infoNode = $paymentInfoNode->addChild('info');
            $infoNode->addChild('infoName', htmlspecialchars($info['infoName']));
            $infoNode->addChild('infoValue', htmlspecialchars($info['infoValue']));
        }

        $xmlString = $xml->asXML();

        // Log or dd
        Log::debug('Generated XML for Payment APi', [
            'xml' => $xmlString,
            'processBillPayment request_id' => $billFetch->request_id
        ]);

        // Encrypt the XML
        $encryptedRequest = CommonSecurityService::encryptTest($xmlString, $credentials['working_key']);

        $url =  "https://stgapi.billavenue.com/billpay/extBillPayCntrl/billPayRequest/xml"
            . "?accessCode={$credentials['access_code']}"
            . "&instituteId={$credentials['agent_institution_id']}"
            . "&requestId={$billFetch->request_id}"
            // . "&requestId=A7X9QPLMN3WZ8R4K2TY5V6UJBHC52621430"
            . "&ver={$credentials['version']}"
            . "&encRequest={$encryptedRequest}";

        // dd($requestId);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
        ));

        $response = curl_exec($curl);

        // $response = curl_exec($curl);
        // dd($response);

        $curlError = curl_error($curl);
        curl_close($curl);

        if ($curlError) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'cURL error: ' . $curlError,
            ], 500);
        }

        // if (CommonSecurityService::isHex($encryptedRequest)) {
        //     $decryptedResponse = CommonSecurityService::decryptTest($encryptedRequest, $credentials['working_key']);
        // } else {
        //     $decryptedResponse = $encryptedRequest;
        // }

        if (CommonSecurityService::isHex($response)) {
            $decryptedResponse = CommonSecurityService::decryptTest($response, $credentials['working_key']);
        } else {
            $decryptedResponse = $response;
        }
        // echo $response;
        // return response()->json([
        //     'ip'      => $ipAddress,
        //     'mac'     => $macAddress,
        //     'payload' => $payload,
        //     'xml'     => $xmlString,
        //     'decrypted_request' => $decryptedResponse,
        // ], 200);

        BillFetches::query()->delete();
        BillerInfos::query()->delete();

        return response($decryptedResponse, 200)
            ->header('Content-Type', 'application/xml');
    }
    
    public function complaintRegister(Request $request)
    {
        $credentials = CommonSecurityService::commonCredentials();
        $requestId   = CommonSecurityService::generateRequestId();
        
        // Backend-assigned agentId
        $agentId = $credentials['agent_id'];
    
        // Validation rules
        $rules = [
            'complaintType' => 'required|string|in:Service,Transaction',
            'complaintDesc' => 'required|string|max:255',
            'complaintDisposition' => 'required|string|max:255',
        ];
    
        if ($request->complaintType === 'Service') {
            $rules['participationType'] = 'required|string|in:AGENT,BILLER';
            $rules['servReason'] = 'required|string|max:255';
    
            if ($request->participationType === 'BILLER') {
                $rules['billerId'] = 'required|string|size:14';
            }
        } elseif ($request->complaintType === 'Transaction') {
            $rules['txnRefId'] = 'required|string|min:12|max:20';
            if ($request->has('billerId')) {
                $rules['billerId'] = 'string|size:14';
            }
        }
    
        // Validate request
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'responseCode' => '400',
                'responseReason' => 'Validation Error',
                'errors' => $validator->errors()
            ], 400);
        }
    
        // Create payload array with backend-assigned agentId if needed
        $payload = $request->all();
    
        if ($request->complaintType === 'Service' && $request->participationType === 'AGENT') {
            $payload['agentId'] = $agentId; // backend assigns agentId
        }
        
        $payload['requestId'] = $requestId;
    
        // ---------------- XML Generation ----------------
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><complaintRegistrationReq/>');
        
            $xml->addChild('complaintType', $request->complaintType);

            // Only add required BBPS fields
            if ($request->complaintType === 'Transaction') {
                $xml->addChild('txnRefId', $request->txnRefId);
            }
        
            $xml->addChild('complaintDesc', htmlspecialchars($request->complaintDesc));
            $xml->addChild('complaintDisposition', htmlspecialchars($request->complaintDisposition));
        
            // Conditionally add agentId for Service + AGENT
            if ($request->complaintType === 'Service' && $request->participationType === 'AGENT') {
                $xml->addChild('agentId', $agentId);
            }
        
            // Conditionally add servReason
            if ($request->complaintType === 'Service' && $request->servReason) {
                $xml->addChild('servReason', htmlspecialchars($request->servReason));
            }
        
            // Conditionally add billerId for Service + BILLER
            if ($request->complaintType === 'Service' && $request->participationType === 'BILLER') {
                $xml->addChild('billerId', $request->billerId);
            }
            
            $xmlString = $xml->asXML();
            
            // dd($xmlString);
            
            $encryptedRequest = CommonSecurityService::encryptTest($xmlString, $credentials['working_key']);
            
            $curl = curl_init();
            
            $url =  "https://stgapi.billavenue.com/billpay/extComplaints/register/xml"
                . "?accessCode={$credentials['access_code']}"
                . "&instituteId={$credentials['agent_institution_id']}"
                . "&requestId={$requestId}"
                . "&ver={$credentials['version']}"
                . "&encRequest={$encryptedRequest}";
            
            curl_setopt_array($curl, array(
              CURLOPT_URL => $url,
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => '',
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 0,
              CURLOPT_FOLLOWLOCATION => true,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => 'POST',
            ));
            
            $response = curl_exec($curl);
            $curlError = curl_error($curl);
            curl_close($curl);
    
            if ($curlError) {
                return response()->json([
                    'status'  => 'failed',
                    'message' => 'cURL error: ' . $curlError,
                ], 500);
            }
            
            if (CommonSecurityService::isHex($response)) {
                $decryptedResponse = CommonSecurityService::decryptTest($response, $credentials['working_key']);
                // dd("if".$decryptedResponse);
            } else {
                $decryptedResponse = $response;
                // dd("else".$decryptedResponse);
            }
            
            // Return XML response
            return response($decryptedResponse, 200)
                   ->header('Content-Type', 'application/xml');
    }
    
    public function complaintStatus(Request $request){
        
        $credentials = CommonSecurityService::commonCredentials();
        $requestId   = CommonSecurityService::generateRequestId();
    
        // Validation rules
        $rules = [
            'complaintType' => 'required|string|in:Service,Transaction',
            'complaintId' => 'required|string|max:255',
        ];
        
        // Validate request
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'responseCode' => '400',
                'responseReason' => 'Validation Error',
                'errors' => $validator->errors()
            ], 400);
        }
    
        // Create payload array with backend-assigned agentId if needed
        $payload = $request->all();
        
        // dd($payload);
        
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><complaintTrackingReq/>');
        $xml->addChild('complaintType', $request->complaintType);
        $xml->addChild('complaintId', $request->complaintId);
        $xmlString = $xml->asXML();

        $encryptedRequest = CommonSecurityService::encryptTest($xmlString, $credentials['working_key']);
        
        $url =  "https://stgapi.billavenue.com/billpay/extComplaints/track/xml"
            . "?accessCode={$credentials['access_code']}"
            . "&instituteId={$credentials['agent_institution_id']}"
            . "&requestId={$requestId}"
            . "&ver={$credentials['version']}"
            . "&encRequest={$encryptedRequest}";
        
        $curl = curl_init();
        
        curl_setopt_array($curl, array(
          CURLOPT_URL => $url,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
        ));
        
        $response = curl_exec($curl);
        $curlError = curl_error($curl);
        curl_close($curl);
    
        if ($curlError) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'cURL error: ' . $curlError,
            ], 500);
        }
        
        if (CommonSecurityService::isHex($response)) {
            $decryptedResponse = CommonSecurityService::decryptTest($response, $credentials['working_key']);
            // dd("if".$decryptedResponse);
        } else {
            $decryptedResponse = $response;
            // dd("else".$decryptedResponse);
        }
            
        // Return XML response
        return response($decryptedResponse, 200)
           ->header('Content-Type', 'application/xml');
        
    }




}