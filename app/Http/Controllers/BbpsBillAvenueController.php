<?php

namespace App\Http\Controllers;

use App\Models\BillerInfos;
use App\Models\BillFetches;
use App\Services\CommonSecurityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SimpleXMLElement;

class BbpsBillAvenueController extends Controller
{
    // multiple biller ids
    public function fetchBillerInfo(Request $request)
    {
        // dump("hello");
        // dump($request->biller_id);
        // Accept raw POST body
        $rawContent = $request->biller_id;
        // dump($rawContent);
        // Split multiple biller IDs (if newline-separated)
        $billerIds = preg_split('/\r\n|\r|\n/', $rawContent);

        $credentials = CommonSecurityService::commonCredentials(); // production
        //  $credentials = CommonSecurityService::commonCredentials1(); //stage
        $requestId = CommonSecurityService::generateRequestId();
        // $requestId   = 'X9P7WQ2LRA8M3TY6B5NDZ4JCE1H52422111'; // for testing specific requestId
        Log::info('fetchBillerInfo', ['requestId' => $requestId, 'credentials' => $credentials]);

        $merchant_data = [
            'billerId' => array_map('trim', $billerIds),
        ];
        // dump($merchant_data);
        $jsonBody = json_encode($merchant_data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        // dump($jsonBody);

        $encryptedRequest = CommonSecurityService::encryptTest($jsonBody, $credentials['working_key']);
        // dump($encryptedRequest);
        //  $decryptedResponse = CommonSecurityService::decryptTest($encryptedRequest, $credentials['working_key']);
        //  dd($decryptedResponse);

        // $url = "https://stgapi.billavenue.com/billpay/extMdmCntrl/mdmRequestNew/json"     //stage uat
        $url = 'https://api.billavenue.com/billpay/extMdmCntrl/mdmRequestNew/json'   // production
            ."?accessCode={$credentials['access_code']}"
            ."&instituteId={$credentials['agent_institution_id']}"
            ."&ver={$credentials['version']}"
            ."&requestId={$requestId}";

        $curl = curl_init();

        curl_setopt_array($curl, [
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

        ]);

        $response = curl_exec($curl);
        // dump($response);
        Log::info('Response : '.$response);
        $curlError = curl_error($curl);
        // dump($curlError);
        curl_close($curl);

        if ($curlError) {
            Log::error('BBPS cURL Error', ['error' => $curlError]);

            return response()->json([
                'status' => 'failed',
                'message' => 'cURL error: '.$curlError,
            ], 500);
        }

        if (CommonSecurityService::isHex($response)) {
            $decryptedResponse = CommonSecurityService::decryptTest($response, $credentials['working_key']);
            Log::info('Decrypted Response (hex): '.$decryptedResponse);
        } else {
            $decryptedResponse = $response;
            Log::info('Decrypted Response (plain): '.$decryptedResponse);
        }

        // dd($decryptedResponse);
        return response($decryptedResponse, 200)
            // ->header('Content-Type', 'application/xml');
            ->header('Content-Type', 'application/json');
    }

    public function fetchBillDetailBackup(Request $request)
    {
        $credentials = $this->commonCredentials();
        $requestId = $this->generateRequestId();
        // $requestId   = "A7X9QPLMN3WZ8R4K2TY5V6UJBHC52621430";
        // dd($requestId);

        Log::info('fetchBillDetails', [$requestId]);

        // Step 1: Base rules
        $baseRules = [
            'billerId' => 'required|string|size:14', // exactly 14 chars
            'customerEmail' => 'nullable|email',
            'customerMobile' => 'required|digits:10',
            'customerAdhaar' => 'nullable|digits:12',
            'customerPan' => 'nullable|string|max:10',
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
                $rules[] = 'digits_between:'.$param->min_length.','.$param->max_length;
            } else {
                $rules[] = 'string';
                if (! empty($param->min_length)) {
                    $rules[] = 'min:'.$param->min_length;
                }
                if (! empty($param->max_length)) {
                    $rules[] = 'max:'.$param->max_length;
                }
            }

            if (! empty($param->reg_ex)) {
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
        } catch (ValidationException $e) {
            throw $e; // rethrow so you still get normal 422 response
        }

        // Step 5: Build inputParams dynamically
        $inputParams = [];
        foreach ($billerParams as $param) {
            $fieldName = Str::camel(strtolower(str_replace(' ', '_', $param->param_name)));
            $inputParams[] = [
                'paramName' => $param->param_name,
                'paramValue' => $validatedData[$fieldName] ?? '',
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
            'requestId' => $requestId,
            'agentId' => $credentials['agent_id'],
            'agentDeviceInfo' => [
                'ip' => $ipAddress,
                'initChannel' => $credentials['payment_channel'],
                'mac' => $macAddress,
            ],
            'customerInfo' => [
                'customerMobile' => $validatedData['customerMobile'],
                'customerEmail' => $validatedData['customerEmail'] ?? '',
                'customerAdhaar' => $validatedData['customerAdhaar'] ?? '',
                'customerPan' => $validatedData['customerPan'] ?? '',
            ],
            'billerId' => $validatedData['billerId'],
            'inputParams' => [
                'input' => $inputParams,
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

        $url = 'https://stgapi.billavenue.com/billpay/extBillCntrl/billFetchRequest/xml'
            ."?accessCode={$credentials['access_code']}"
            ."&instituteId={$credentials['agent_institution_id']}"
            ."&requestId={$requestId}"
            ."&ver={$credentials['version']}"
            ."&encRequest={$encryptedRequest}";

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
        ]);

        $response = curl_exec($curl);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($curlError) {
            return response()->json([
                'status' => 'failed',
                'message' => 'cURL error: '.$curlError,
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

        $savedData = BillFetches::create([
            'request_id' => $requestId,
            'biller_info_id' => $billerInfo->id,
            'biller_response' => json_encode($billerResponse),
            'additional_info' => json_encode($additionalInfo),
            'input_params' => json_encode($inputParams),
        ]);

        // dd($billerResponse, $additionalInfo, $savedData->toArray());

        // DB::table('biller_info')->delete();

        return response($decryptedResponse, 200)
            ->header('Content-Type', 'application/xml');
    }

    public function fetchBillDetails(Request $request)
    {
        // dd("helllo");
        // dd($request);
        // Static data (will be used instead of DB)
        $validatedData = [
            'billerId' => $request->billerId ?? 'HOTS00000NATTH',  // 'DUMMY0000DIG08',
            'customerMobile' => $request->customerMobile ?? '9284210056',
            'customerEmail' => $request->customerEmail ?? 'khanamaanak1@gmail.com',
            // 'customerAdhaar' => $request->customerAdhaar ?? '548550008000',
            // 'customerPan'    => $request->customerPan ?? 'AAAPZ1234C',
            // 'customerId'     => $request->customerId ?? 'h696077',
            // 'policyNumber'  => $request->customerId ?? '52157852',
            'mobileNumber' => $request->customerMobile ?? '9284210056',
            'plan' => $request->plan ?? 'Premium 1yr | 4 devices / 4K / NO ads (Rs.1499/yr)',
        ];
        //   dump($validatedData);
        // Skip dynamic DB rules for now
        $inputParams = [
            // ["paramName" => "customerId", "paramValue" => $validatedData['customerId']],
            ['paramName' => 'Mobile Number', 'paramValue' => $validatedData['mobileNumber']],
            ['paramName' => 'Plan', 'paramValue' => $validatedData['plan']],

            // ["paramName" => "customerMobile", "paramValue" => $validatedData['customerMobile']],
            // ["paramName" => "customerEmail", "paramValue" => $validatedData['customerEmail']],
            // ["paramName" => "customerAdhaar", "paramValue" => $validatedData['customerAdhaar']],
            // ["paramName" => "customerPan", "paramValue" => $validatedData['customerPan']],
            // ["paramName" => "customerId", "paramValue" => $validatedData['customerId']],

        ];
        //   dump($inputParams);
        $requestId = CommonSecurityService::generateRequestId();
        $credentials = CommonSecurityService::commonCredentials();

        $payload = [
            'requestId' => $requestId,
            'agentId' => $credentials['agent_id'],
            'agentDeviceInfo' => [
                'ip' => $request->ip(),
                'initChannel' => $credentials['payment_channel'],
                'mac' => 'A1-B2-C3-D4-E5-F6',
            ],
            'customerInfo' => [
                'customerMobile' => $validatedData['customerMobile'],
                'customerEmail' => $validatedData['customerEmail'],
                // "customerAdhaar" => $validatedData['customerAdhaar'],
                // "customerPan" => $validatedData['customerPan'],
                'mobileNumber' => $validatedData['mobileNumber'],
                'plan' => $validatedData['plan'],

            ],
            'billerId' => $validatedData['billerId'],
            'inputParams' => ['input' => $inputParams],
        ];
        // dump($payload);
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><billFetchRequest/>');

        // Agent Device Info
        $deviceInfo = $xml->addChild('agentDeviceInfo');
        $deviceInfo->addChild('app', 'tripozo'); // optional depending on doc
        $deviceInfo->addChild('imei', '000000000000000');
        $deviceInfo->addChild('initChannel', $payload['agentDeviceInfo']['initChannel']);
        $deviceInfo->addChild('ip', $payload['agentDeviceInfo']['ip']);
        $deviceInfo->addChild('os', 'android'); // optional

        // Agent Id
        $xml->addChild('agentId', $payload['agentId']);

        // Biller Id
        $xml->addChild('billerId', $payload['billerId']);

        // Customer Info
        $customerInfoNode = $xml->addChild('customerInfo');
        $customerInfoNode->addChild('customerMobile', $validatedData['customerMobile']);
        $customerInfoNode->addChild('customerEmail', $validatedData['customerEmail']);
        // $customerInfoNode->addChild('customerPan', $validatedData['customerPan']);

        // Input Params
        $inputParamsNode = $xml->addChild('inputParams');
        foreach ($inputParams as $input) {
            // $inputNode = $inputParamsNode->addChild('input');
            // // $paramName = ($input['paramName'] === 'customerId') ? 'CustomerId' : $input['paramName'];
            // $inputNode->addChild('paramName', $paramName);
            // $inputNode->addChild('paramValue', $input['paramValue']);

            $inputNode = $inputParamsNode->addChild('input');
            $inputNode->addChild('paramName', $input['paramName']);
            $inputNode->addChild('paramValue', $input['paramValue']);
        }

        $xmlString = $xml->asXML();
        // dump($xmlString);

        $encryptedRequest = CommonSecurityService::encryptTest($xmlString, $credentials['working_key']);

        $url = 'https://api.billavenue.com/billpay/extBillCntrl/billFetchRequest/xml' // staged uat
        // $url = "https://api.billavenue.com/billpay/extBillCntrl/billFetchRequest/xml"    //production
            ."?accessCode={$credentials['access_code']}"
            ."&instituteId={$credentials['agent_institution_id']}"
            ."&requestId={$requestId}"
            ."&ver={$credentials['version']}"
            ."&encRequest={$encryptedRequest}";

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
        ]);

        $response = curl_exec($curl);
        // dump($response);
        Log::info($response);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($curlError) {
            return response()->json(['status' => 'failed', 'message' => $curlError], 500);
        }

        $decryptedResponse = CommonSecurityService::isHex($response)
            ? CommonSecurityService::decryptTest($response, $credentials['working_key'])
            : $response;
        Log::info($decryptedResponse);
        session([
            'bill_fetch_request_id' => $requestId,
            'bill_fetch_response' => $decryptedResponse,
        ]);

        // dd(session()->all());
        // dd($decryptedResponse);
        return response($decryptedResponse, 200)->header('Content-Type', 'application/xml');
    }

    public function processBillPayment(Request $request)
    {

        $credentials = CommonSecurityService::commonCredentials();
        $requestId = CommonSecurityService::generateRequestId();

        // Step 1: Validate request
        $baseRules = [
            'billerId' => 'required|string|size:14',
            'REMITTER_NAME' => 'required|string',
            'customerEmail' => 'nullable|email',
            'customerMobile' => 'required|digits:10',
            'customerAdhaar' => 'nullable|digits:12',
            'customerPan' => 'nullable|string|max:10',
            'paymentMode' => 'required|string',
            'quickPay' => 'required|string',
            'splitPay' => 'required|string',
            'paymentInfo' => 'required|array',
            'paymentInfo.*.infoName' => 'required|string',
            'paymentInfo.*.infoValue' => 'required|string',
        ];

        $validatedData = Validator::make($request->all(), $baseRules)->validate();

        // Step 2: Get bill fetch from session
        $billFetchResponse = session('bill_fetch_response');
        $billFetchRequestId = session('bill_fetch_request_id');
        Log::info($billFetchResponse);
        Log::info($billFetchRequestId);

        if (! $billFetchResponse || ! $billFetchRequestId) {
            return response()->json([
                'status' => 'failed',
                'message' => 'No bill fetched. Please fetch bill first.',
            ], 400);
        }

        // Parse XML stored in session
        $billFetchXml = simplexml_load_string($billFetchResponse);
        Log::info('xml', [(string) $billFetchXml->asXML()]);
        // Step 3: Prepare inputParams
        $inputParams = [];
        foreach ($billFetchXml->inputParams->input as $input) {
            $inputParams[] = [
                'paramName' => (string) $input->paramName,
                'paramValue' => (string) $input->paramValue,
            ];
        }

        // Step 4: Prepare billerResponse
        $billerResponse = [
            'billAmount' => (int) $billFetchXml->billerResponse->billAmount,
            'billDate' => (string) $billFetchXml->billerResponse->billDate,
            'customerName' => (string) $billFetchXml->billerResponse->customerName ?? 'Test User', // hardcode for testing
            'dueDate' => (string) $billFetchXml->billerResponse->dueDate ?? date('Y-m-d', strtotime('+10 days')), // temporary
            'billNumber' => (string) $billFetchXml->billerResponse->billNumber ?? 'NA', // hardcode if not available
            'billPeriod' => (string) $billFetchXml->billerResponse->billPeriod ?? 'NA',
            'amountOptions' => ['option' => []],
        ];

        if (isset($billFetchXml->billerResponse->amountOptions->option)) {
            foreach ($billFetchXml->billerResponse->amountOptions->option as $opt) {
                $billerResponse['amountOptions']['option'][] = [
                    'amountName' => (string) $opt->amountName,
                    'amountValue' => (string) $opt->amountValue,
                ];
            }
        }

        // Step 5: Prepare additionalInfo
        $additionalInfo = ['info' => []];
        if (isset($billFetchXml->additionalInfo->info)) {
            foreach ($billFetchXml->additionalInfo->info as $info) {
                $additionalInfo['info'][] = [
                    'infoName' => (string) $info->infoName,
                    'infoValue' => (string) $info->infoValue,
                ];
            }
        }

        // Step 6: IP/MAC
        $ipAddress = $request->ip();
        $macAddress = 'A1-B2-C3-D4-E5-F6'; // static for testing

        // Step 7: Enforce PAN for Cash >50,000
        $billAmount = $billerResponse['billAmount'];

        if ($billAmount >= 50000 && $validatedData['paymentMode'] === 'Cash') {
            if (empty($validatedData['customerPan'])) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'PAN is required for Cash transactions of ₹50,000 or more.',
                ], 400);
            }
            // $validatedData['paymentMode'] = 'BankAccount'; // BBPS requirement
        }

        // Step 8: Biller Adhoc
        $billerInfo = (object) ['biller_adhoc' => 'true'];
        $paymentAccountInfo = null;
        if ($billAmount >= 50000 || $validatedData['paymentMode'] === 'CreditCard') {
            $paymentAccountInfo = [
                'accountType' => $billAmount >= 50000 && $validatedData['paymentMode'] === 'Cash'
                            ? 'savings'
                            : $validatedData['paymentMode'], // BankAccount / CreditCard
                'accountNumber' => $validatedData['accountNumber'] ?? '1745917325', // test
                'bankCode' => $validatedData['bankCode'] ?? 'KKBK0000629',             // optional
                'expiryDate' => $validatedData['expiryDate'] ?? '',               // for CC
            ];
        }

        // if ($paymentAccountInfo) {
        //     $payload['paymentAccountInfo'] = $paymentAccountInfo;
        // }

        // Step 9: Build payload
        $payload = [
            'agentId' => $credentials['agent_id'],
            'paymentRefId' => CommonSecurityService::generatePaymentRefId(),
            'billerAdhoc' => $billerInfo->biller_adhoc,
            'agentDeviceInfo' => [
                'ip' => $ipAddress,
                'initChannel' => $credentials['payment_channel'],
                'mac' => $macAddress,
            ],
            'customerInfo' => [
                'REMITTER_NAME' => $validatedData['REMITTER_NAME'],
                'customerMobile' => $validatedData['customerMobile'],
                'customerEmail' => $validatedData['customerEmail'] ?? '',
                'customerAdhaar' => $validatedData['customerAdhaar'] ?? '',
                // "customerPan"    => ($billAmount >= 50000 && $validatedData['paymentMode'] === 'BankAccount')
                //                     ? 'AAAPZ1234C' : '',
                'customerPan' => 'AAAPZ1234C',
            ],
            'billerId' => $validatedData['billerId'],
            'inputParams' => ['input' => $inputParams],
            'billerResponse' => $billerResponse,
            'additionalInfo' => $additionalInfo,
            'amountInfo' => [
                'amount' => $billAmount,
                'currency' => '365',
                'custConvFee' => '0',
            ],
            'paymentMethod' => [
                'paymentMode' => $validatedData['paymentMode'],
                'quickPay' => $validatedData['quickPay'],
                'splitPay' => $validatedData['splitPay'],
            ],
            'paymentInfo' => $validatedData['paymentInfo'],
        ];

        if ($paymentAccountInfo) {
            $payload['paymentAccountInfo'] = $paymentAccountInfo;
        }
        Log::info('payload', [$payload]);
        // Step 10: Convert payload to XML
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><billPaymentRequest/>');
        $xml->addChild('agentId', $payload['agentId']);
        $xml->addChild('paymentRefId', $payload['paymentRefId']);
        $xml->addChild('billerAdhoc', $payload['billerAdhoc']);

        $agentDeviceInfoNode = $xml->addChild('agentDeviceInfo');
        foreach ($payload['agentDeviceInfo'] as $k => $v) {
            $agentDeviceInfoNode->addChild($k, htmlspecialchars($v));
        }

        $customerInfoNode = $xml->addChild('customerInfo');
        foreach ($payload['customerInfo'] as $k => $v) {
            $customerInfoNode->addChild($k, htmlspecialchars($v));
        }

        $xml->addChild('billerId', $payload['billerId']);

        $inputParamsNode = $xml->addChild('inputParams');
        foreach ($payload['inputParams']['input'] as $input) {
            $inputNode = $inputParamsNode->addChild('input');
            $inputNode->addChild('paramName', htmlspecialchars($input['paramName']));
            $inputNode->addChild('paramValue', htmlspecialchars($input['paramValue']));
        }

        $billerResponseNode = $xml->addChild('billerResponse');
        foreach ($payload['billerResponse'] as $key => $value) {
            if (is_array($value) && $key === 'amountOptions' && isset($value['option'])) {
                $amountOptionsNode = $billerResponseNode->addChild('amountOptions');
                foreach ($value['option'] as $option) {
                    $optionNode = $amountOptionsNode->addChild('option');
                    $optionNode->addChild('amountName', htmlspecialchars($option['amountName']));
                    $optionNode->addChild('amountValue', htmlspecialchars($option['amountValue']));
                }
            } elseif (! is_array($value)) {
                $billerResponseNode->addChild($key, htmlspecialchars($value));
            }
        }

        if (! empty($payload['additionalInfo']['info'])) {
            $additionalInfoNode = $xml->addChild('additionalInfo');
            foreach ($payload['additionalInfo']['info'] as $info) {
                $infoNode = $additionalInfoNode->addChild('info');
                $infoNode->addChild('infoName', htmlspecialchars($info['infoName']));
                $infoNode->addChild('infoValue', htmlspecialchars($info['infoValue']));
            }
        }

        $amountInfoNode = $xml->addChild('amountInfo');
        foreach ($payload['amountInfo'] as $k => $v) {
            $amountInfoNode->addChild($k, htmlspecialchars($v));
        }

        $paymentMethodNode = $xml->addChild('paymentMethod');
        foreach ($payload['paymentMethod'] as $k => $v) {
            $paymentMethodNode->addChild($k, htmlspecialchars($v));
        }

        $paymentInfoNode = $xml->addChild('paymentInfo');
        foreach ($payload['paymentInfo'] as $info) {
            $infoNode = $paymentInfoNode->addChild('info');
            $infoNode->addChild('infoName', htmlspecialchars($info['infoName']));
            $infoNode->addChild('infoValue', htmlspecialchars($info['infoValue']));
        }
        if (! empty($payload['paymentAccountInfo'])) {
            $accountNode = $xml->addChild('paymentAccountInfo');
            foreach ($payload['paymentAccountInfo'] as $k => $v) {
                $accountNode->addChild($k, htmlspecialchars($v));
            }
        }

        $xmlString = $xml->asXML();

        Log::debug('Generated XML for Payment API', [
            'xml' => $xmlString,
            'request_id' => $billFetchRequestId,
        ]);
        // Step 11: Encrypt XML
        $encryptedRequest = CommonSecurityService::encryptTest($xmlString, $credentials['working_key']);

        // Step 12: cURL call
        $url = 'https://stgapi.billavenue.com/billpay/extBillPayCntrl/billPayRequest/xml'   // staged uat
        // $url = "https://api.billavenue.com/billpay/extBillPayCntrl/billPayRequest/xml"   //production
            ."?accessCode={$credentials['access_code']}"
            ."&instituteId={$credentials['agent_institution_id']}"
            ."&requestId={$billFetchRequestId}"
            ."&ver={$credentials['version']}"
            ."&encRequest={$encryptedRequest}";

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
        ]);

        $response = curl_exec($curl);
        Log::info($response);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($curlError) {
            return response()->json([
                'status' => 'failed',
                'message' => 'cURL error: '.$curlError,
            ], 500);
        }

        // Step 13: Decrypt response
        $decryptedResponse = CommonSecurityService::isHex($response)
            ? CommonSecurityService::decryptTest($response, $credentials['working_key'])
            : $response;

        Log::info($decryptedResponse);

        // Step 14: Return XML response
        return response($decryptedResponse, 200)->header('Content-Type', 'application/xml');
    }

    public function helloFromBBPS(Request $request)
    {
        return response()->json([
            'status' => true,
            'ip' => $request->ip(),
        ]);
    }
}
