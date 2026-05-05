<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CommonSecurityService;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use App\Models\BillerInformation;
use App\Models\BharatConnectMdmTest;
use App\Models\BfBillFetch;
use App\Models\BillFetch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SimpleXMLElement;

class BillFetchController extends Controller
{
    //XML defunct method
    public function fetchBillDetails(Request $request){
        $credentials = CommonSecurityService::commonCredentials();
        $requestId   = CommonSecurityService::generateRequestId();
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
        $billerParams = DB::table('biller_info')
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
        $encryptedRequest = CommonSecurityService::encryptTest($xmlString, $credentials['working_key']);
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

        if (CommonSecurityService::isHex($response)) {
            $decryptedResponse =  CommonSecurityService::decryptTest($response, $credentials['working_key']);
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

        $billerInfo = BillerInformation::first();

        $savedData =  BillFetch::create([
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
    
    //if isOptional is true still error is coming in response //defunct
    public function billFetch_priority_0(Request $request){
        try {
            $credentials = CommonSecurityService::commonCredentials();
            $requestId   = CommonSecurityService::generateRequestId();
    
            // Base validation for customer fields
            $baseRules = [
                'billerId'       => 'required|string|size:14',
                'customerMobile' => 'required|digits:10',
                'customerEmail'  => 'nullable|email',
                'customerAdhaar' => 'nullable|digits:12',
                'customerPan'    => 'nullable|string|max:10',
            ];
    
            $biller = BharatConnectMdmTest::where('blr_id', $request->billerId)->first();
            if (!$biller) {
                return response()->json(['status' => false, 'message' => 'Invalid billerId'], 404);
            }
    
            $billerResponse = is_string($biller->biller_response) ? json_decode($biller->biller_response, true) : $biller->biller_response;
            $paramsGroups = $billerResponse['billerInputParams'] ?? [];
    
            // Collect expected parameters
            $expectedParams = [];
            foreach ($paramsGroups as $group) {
                foreach ($group['paramsList'] ?? [] as $param) {
                    $expectedParams[] = $param['paramName'];
                }
            }
    
            // Check if request contains only expected params
            $requestKeys = array_keys($request->all());
            $extraKeys = array_diff($requestKeys, array_merge($expectedParams, array_keys($baseRules)));
    
            if (!empty($extraKeys)) {
                return response()->json([
                    'status' => false,
                    'message' => "Invalid input parameters for billerId {$request->billerId}",
                    'expectedParams' => $expectedParams,
                    'receivedExtra' => $extraKeys
                ], 422);
            }
    
            // Check required params
            $missingParams = [];
            foreach ($paramsGroups as $group) {
                foreach ($group['paramsList'] ?? [] as $param) {
                    if (strtolower($param['isOptional'] ?? 'false') === 'false' && !$request->has($param['paramName'])) {
                        $missingParams[] = $param['paramName'];
                    }
                }
            }
    
            if (!empty($missingParams)) {
                return response()->json([
                    'status' => false,
                    'message' => "Missing required parameters for billerId {$request->billerId}",
                    'missingParams' => $missingParams
                ], 422);
            }
    
            $validatedData = $request->validate($baseRules);
    
            // Prepare inputParams array
            $inputParams = [];
            foreach ($expectedParams as $paramName) {
                if ($request->has($paramName)) {
                    $value = $request->input($paramName);
                    $inputParams[] = [
                        'paramName' => $paramName,
                        'paramValue' => is_numeric($value) ? (0+$value) : $value
                    ];
                }
            }
    
            $payload = [
                "agentId" => $credentials['agent_id'],
                "billerAdhoc" => filter_var($billerResponse['billerAdhoc'] ?? false, FILTER_VALIDATE_BOOLEAN),
                "agentDeviceInfo" => [
                    "ip" => $request->ip(),
                    "initChannel" => $credentials['payment_channel'],
                    "mac" => 'A1-B2-C3-D4-E5-F6',
                ],
                "customerInfo" => [
                    "customerMobile" => $validatedData['customerMobile'],
                    "customerEmail" => $validatedData['customerEmail'] ?? '',
                    "customerAdhaar" => $validatedData['customerAdhaar'],
                    "customerPan" => $validatedData['customerPan'] ?? '',
                ],
                "billerId" => $validatedData['billerId'],
                "inputParams" => ["input" => $inputParams]
            ];
    
            $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);
            
            $encryptedRequest = CommonSecurityService::encryptTest($jsonPayload, $credentials['working_key']);
            
            // dd($encryptedRequest);
    
            $url =  "https://stgapi.billavenue.com/billpay/extBillCntrl/billFetchRequest/json"
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
                $decryptedString =  CommonSecurityService::decryptTest($response, $credentials['working_key']);
                $decryptedResponse = json_decode($decryptedString, true) ?? $decryptedString;
            } else {
                $decryptedResponse = $response;
            }
            
            return response()->json([
                // 'status'      => true,
                // 'requestId'   => $requestId,
                // 'credentials' => $credentials,
                // 'validated'   => $validatedData,
                'decryptedResponse'   => $decryptedResponse
            ]);
    
    
        } catch (\Illuminate\Validation\ValidationException $ex) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation Error',
                'errors'  => $ex->errors(),
            ], 422);
    
        } catch (\Exception $ex) {
            return response()->json([
                'status'  => false,
                'message' => $ex->getMessage(),
            ], 500);
        }
}
    
    //success to save data in db in bf_bill_fetch table //successfully running
    public function billFetch(Request $request){
        try {
            $credentials = CommonSecurityService::commonCredentials();
            $requestId   = CommonSecurityService::generateRequestId();
    
            // Base validation for customer fields
            $baseRules = [
                'billerId'       => 'required|string|size:14',
                'customerMobile' => 'required|digits:10',
                'customerEmail'  => 'nullable|email',
                'customerAdhaar' => 'nullable|digits:12',
                'customerPan'    => 'nullable|string|max:10',
            ];
    
            $biller = BharatConnectMdmTest::where('blr_id', $request->billerId)->first();
            if (!$biller) {
                return response()->json(['status' => false, 'message' => 'Invalid billerId'], 404);
            }
    
            $billerResponse = is_string($biller->biller_response) ? json_decode($biller->biller_response, true) : $biller->biller_response;
            $paramsGroups = $billerResponse['billerInputParams'] ?? [];
    
            // Collect expected parameters
            $expectedParams = [];
            foreach ($paramsGroups as $group) {
                foreach ($group['paramsList'] ?? [] as $param) {
                    $expectedParams[] = $param['paramName'];
                }
            }
    
            // Check if request contains only expected params
            $requestKeys = array_keys($request->all());
            $extraKeys = array_diff($requestKeys, array_merge($expectedParams, array_keys($baseRules)));
    
            if (!empty($extraKeys)) {
                return response()->json([
                    'status' => false,
                    'message' => "Invalid input parameters for billerId {$request->billerId}",
                    'expectedParams' => $expectedParams,
                    'receivedExtra' => $extraKeys
                ], 422);
            }
    
            // New Logic: If ANY params exist → ALL become mandatory
            $mandatoryParams = [];
            
            foreach ($paramsGroups as $group) {
                foreach ($group['paramsList'] ?? [] as $param) {
                    $mandatoryParams[] = $param['paramName'];
                }
            }
            
            $requestInputKeys = array_keys($request->all());
            
            // Check request contains all mandatory params
            $missingParams = array_diff($mandatoryParams, $requestInputKeys);
            
            if (!empty($mandatoryParams) && !empty($missingParams)) {
                return response()->json([
                    'status' => false,
                    'message' => "Consumer input required for billerId {$request->billerId}",
                    'missingParams' => array_values($missingParams)
                ], 422);
            }
    
            $validatedData = $request->validate($baseRules);
    
            // Prepare inputParams array
            $inputParams = [];
            foreach ($expectedParams as $paramName) {
                if ($request->has($paramName)) {
                    $value = $request->input($paramName);
                    $inputParams[] = [
                        'paramName' => $paramName,
                        'paramValue' => is_numeric($value) ? (0+$value) : $value
                    ];
                }
            }
            
            $savedData = BfBillFetch::create([
                'blr_id' => $validatedData['billerId'],
                'request_id' => $requestId,
            ]);
            
            $payload = [
                "agentId" => $credentials['agent_id'],
                "billerAdhoc" => filter_var($billerResponse['billerAdhoc'] ?? false, FILTER_VALIDATE_BOOLEAN),
                "agentDeviceInfo" => [
                    "ip" => $request->ip(),
                    "initChannel" => $credentials['payment_channel'],
                    "mac" => 'A1-B2-C3-D4-E5-F6',
                ],
                "customerInfo" => [
                    "customerMobile" => $validatedData['customerMobile'],
                    "customerEmail" => $validatedData['customerEmail'] ?? '',
                    "customerAdhaar" => $validatedData['customerAdhaar'] ?? '',
                    "customerPan" => $validatedData['customerPan'] ?? '',
                ],
                "billerId" => $validatedData['billerId'],
                "inputParams" => ["input" => $inputParams]
            ];
    
            $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);
            
            $encryptedRequest = CommonSecurityService::encryptTest($jsonPayload, $credentials['working_key']);
            
            $url =  "https://stgapi.billavenue.com/billpay/extBillCntrl/billFetchRequest/json"
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
                $decryptedString =  CommonSecurityService::decryptTest($response, $credentials['working_key']);
                $decryptedResponse = json_decode($decryptedString, true) ?? $decryptedString;
            } else {
                $decryptedResponse = $response;
            }
            
            if ($savedData) {
                $cleanResponse = $decryptedResponse['decryptedResponse'] ?? $decryptedResponse;
            
                $savedData->update([
                    'bill_fetch_response' => $cleanResponse,
                    'input_params'        => ['inputParams' => $cleanResponse['inputParams'] ?? null],
                    'biller_response'     => ['billerResponse' => $cleanResponse['billerResponse'] ?? null],
                    'additional_info'     => ['additionalInfo' => $cleanResponse['additionalInfo'] ?? null],
                ]);
            }
            
            return response()->json([
                // 'status'      => true,
                // 'requestId'   => $requestId,
                // 'credentials' => $credentials,
                // 'validated'   => $validatedData,
                'decryptedResponse'   => $decryptedResponse
            ]);
        } catch (\Illuminate\Validation\ValidationException $ex) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation Error',
                'errors'  => $ex->errors(),
            ], 422);
        } catch (\Exception $ex) {
            return response()->json([
                'status'  => false,
                'message' => $ex->getMessage(),
            ], 500);
        }
    }

}
