<?php

namespace App\Http\Controllers;

use App\Models\BfBillFetch;
use App\Models\BfBillFetchProd;
use App\Models\BharatConnectMdm;
use App\Models\BharatConnectMdmTest;
use App\Services\CommonSecurityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BillValidationController extends Controller
{
    // STAGED LEVEL APIS
    // json current api
    public function billProcess(Request $request)
    {
        try {
            $biller = BharatConnectMdmTest::where('blr_id', $request->billerId)->first();

            if (! $biller) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid billerId',
                ], 404);
            }

            // Decode biller response
            $billerResponse = is_string($biller->biller_response)
                ? json_decode($biller->biller_response, true)
                : $biller->biller_response;

            // Fetch requirement
            $fetchRequirement = strtoupper($billerResponse['billerFetchRequiremet'] ?? 'MANDATORY');

            // Decide which API to call
            if (in_array($fetchRequirement, ['MANDATORY', 'OPTIONAL'])) {
                $apiType = 'billFetch';
                $response = $this->billFetch($request);
            } else {
                $apiType = 'billValidationApi';
                $response = $this->billValidationApi($request);
            }

            // Extract data + HTTP code from response
            $data = $response->getData(true);
            $statusCode = $response->getStatusCode();

            // Determine success based on biller logic
            $inner = $data['decryptedResponse'] ?? [];
            $responseCode = $inner['responseCode'] ?? null;

            // Biller success rule
            $isSuccess = ($responseCode === '000');

            // If biller returned success but statusCode not 200 → enforce 200
            if ($isSuccess && $statusCode !== 200) {
                $statusCode = 200;
            }

            // If biller returned failure but HTTP 200 → convert to 422
            if (! $isSuccess && $statusCode === 200) {
                $statusCode = 422;
            }

            return response()->json([
                'status' => $isSuccess,
                'billerId' => $request->billerId,
                'calledApi' => $apiType,
                'result' => $data,
            ], $statusCode);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // json current api (for bill fetch)
    public function billFetch(Request $request)
    {
        try {
            $credentials = CommonSecurityService::commonCredentials1();
            $requestId = CommonSecurityService::generateRequestId();

            // Base validation for customer fields
            $baseRules = [
                'billerId' => 'required|string|size:14',
                'customerMobile' => 'required|digits:10',
                'customerEmail' => 'nullable|email',
                'customerAdhaar' => 'nullable|digits:12',
                'customerPan' => 'nullable|string|max:10',
            ];

            $biller = BharatConnectMdmTest::where('blr_id', $request->billerId)->first();
            if (! $biller) {
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

            if (! empty($extraKeys)) {
                return response()->json([
                    'status' => false,
                    'message' => "Invalid input parameters for billerId {$request->billerId}",
                    'expectedParams' => $expectedParams,
                    'receivedExtra' => $extraKeys,
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

            if (! empty($mandatoryParams) && ! empty($missingParams)) {
                return response()->json([
                    'status' => false,
                    'message' => "Consumer input required for billerId {$request->billerId}",
                    'missingParams' => array_values($missingParams),
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
                        'paramValue' => is_numeric($value) ? ($value) : $value,
                    ];
                }
            }

            $savedData = BfBillFetch::create([
                'user_id' => auth()->id(),
                'blr_id' => $validatedData['billerId'],
                'request_id' => $requestId,
            ]);

            $payload = [
                'agentId' => $credentials['agent_id'],
                'billerAdhoc' => filter_var($billerResponse['billerAdhoc'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'agentDeviceInfo' => [
                    'ip' => $request->ip(),
                    'initChannel' => $credentials['payment_channel'],
                    'mac' => 'A1-B2-C3-D4-E5-F6',
                ],
                'customerInfo' => [
                    'customerMobile' => $validatedData['customerMobile'],
                    'customerEmail' => $validatedData['customerEmail'] ?? '',
                    'customerAdhaar' => $validatedData['customerAdhaar'] ?? '',
                    'customerPan' => $validatedData['customerPan'] ?? '',
                ],
                'billerId' => $validatedData['billerId'],
                'inputParams' => ['input' => $inputParams],
            ];

            $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);

            $encryptedRequest = CommonSecurityService::encryptTest($jsonPayload, $credentials['working_key']);

            $url = 'https://stgapi.billavenue.com/billpay/extBillCntrl/billFetchRequest/json'   // UAT STAGED
            // $url =  "https://api.billavenue.com/billpay/extBillCntrl/billFetchRequest/json"   //PRODUCTION
                ."?accessCode={$credentials['access_code']}"
                ."&instituteId={$credentials['agent_institution_id']}"
                ."&requestId={$requestId}"
                ."&ver={$credentials['version']}"
                ."&encRequest={$encryptedRequest}";

            Log::info('Bill Fetch Url Request:', ['url' => $url]);

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

            if (CommonSecurityService::isHex($response)) {
                $decryptedString = CommonSecurityService::decryptTest($response, $credentials['working_key']);
                $decryptedResponse = json_decode($decryptedString, true) ?? $decryptedString;
            } else {
                $decryptedResponse = $response;
            }

            if ($savedData) {
                $cleanResponse = $decryptedResponse['decryptedResponse'] ?? $decryptedResponse;

                $savedData->update([
                    'bill_fetch_response' => $cleanResponse,
                    'input_params' => ['inputParams' => $cleanResponse['inputParams'] ?? null],
                    'biller_response' => ['billerResponse' => $cleanResponse['billerResponse'] ?? null],
                    'additional_info' => ['additionalInfo' => $cleanResponse['additionalInfo'] ?? null],
                ]);
            }

            Log::info('Bill Fetch Decrypted Response:', ['response' => $decryptedResponse]);

            return response()->json([
                // 'status'      => true,
                // 'requestId'   => $requestId,
                // 'credentials' => $credentials,
                // 'validated'   => $validatedData,
                'decryptedResponse' => $decryptedResponse,
            ]);
        } catch (ValidationException $ex) {
            return response()->json([
                'status' => false,
                'message' => 'Validation Error',
                'errors' => $ex->errors(),
            ], 422);
        } catch (\Exception $ex) {
            return response()->json([
                'status' => false,
                'message' => $ex->getMessage(),
            ], 500);
        }
    }

    // json current api (for bill validation)
    public function billValidationApi(Request $request)
    {
        try {
            $credentials = CommonSecurityService::commonCredentials1();
            $requestId = CommonSecurityService::generateRequestId();

            // Base validation for customer fields
            $baseRules = [
                'billerId' => 'required|string|size:14',
                // 'customerMobile' => 'required|digits:10',
                // 'customerEmail'  => 'nullable|email',
                // 'customerAdhaar' => 'nullable|digits:12',
                // 'customerPan'    => 'nullable|string|max:10',
            ];

            $biller = BharatConnectMdmTest::where('blr_id', $request->billerId)->first();
            if (! $biller) {
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

            if (! empty($extraKeys)) {
                return response()->json([
                    'status' => false,
                    'message' => "Invalid input parameters for billerId {$request->billerId}",
                    'expectedParams' => $expectedParams,
                    'receivedExtra' => $extraKeys,
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

            if (! empty($mandatoryParams) && ! empty($missingParams)) {
                return response()->json([
                    'status' => false,
                    'message' => "Consumer input required for billerId {$request->billerId}",
                    'missingParams' => array_values($missingParams),
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
                        'paramValue' => is_numeric($value) ? ($value) : $value,
                    ];
                }
            }
            // dd(auth()->id());
            $savedData = BfBillFetch::create([
                'user_id' => auth()->id(),
                'blr_id' => $validatedData['billerId'],
                'request_id' => $requestId,
            ]);

            $payload = [
                'agentId' => $credentials['agent_id'],
                'billerAdhoc' => filter_var($billerResponse['billerAdhoc'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'agentDeviceInfo' => [
                    'ip' => $request->ip(),
                    'initChannel' => $credentials['payment_channel'],
                    'mac' => 'A1-B2-C3-D4-E5-F6',
                ],
                // "customerInfo" => [
                //     "customerMobile" => $validatedData['customerMobile'],
                //     "customerEmail" => $validatedData['customerEmail'] ?? '',
                //     "customerAdhaar" => $validatedData['customerAdhaar'] ?? '',
                //     "customerPan" => $validatedData['customerPan'] ?? '',
                // ],
                'billerId' => $validatedData['billerId'],
                'inputParams' => ['input' => $inputParams],
            ];

            $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);

            $encryptedRequest = CommonSecurityService::encryptTest($jsonPayload, $credentials['working_key']);

            $url = 'https://stgapi.billavenue.com/billpay/extBillValCntrl/billValidationRequest/json'   // UAT STAGED
            // $url =  "https://api.billavenue.com/billpay/extBillValCntrl/billValidationRequest/json"   //PRODUCTION
                ."?accessCode={$credentials['access_code']}"
                ."&instituteId={$credentials['agent_institution_id']}"
                ."&requestId={$requestId}"
                ."&ver={$credentials['version']}"
                ."&encRequest={$encryptedRequest}";

            Log::info('Bill Validation Url Request:', ['url' => $url]);

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

            if (CommonSecurityService::isHex($response)) {
                $decryptedString = CommonSecurityService::decryptTest($response, $credentials['working_key']);
                $decryptedResponse = json_decode($decryptedString, true) ?? $decryptedString;
            } else {
                $decryptedResponse = $response;
            }

            if ($savedData) {
                $cleanResponse = $decryptedResponse['decryptedResponse'] ?? $decryptedResponse;

                $savedData->update([
                    'bill_fetch_response' => $cleanResponse,
                    'input_params' => ['inputParams' => $cleanResponse['inputParams'] ?? null],
                    'biller_response' => ['billerResponse' => $cleanResponse['billerResponse'] ?? null],
                    'additional_info' => ['additionalInfo' => $cleanResponse['additionalInfo'] ?? null],
                ]);
            }

            // dd($cleanResponse);
            Log::info('Bill Validation Decrypted Response:', ['response' => $decryptedResponse]);

            return response()->json([
                // 'status'      => true,
                // 'requestId'   => $requestId,
                // 'credentials' => $credentials,
                // 'validated'   => $validatedData,
                'decryptedResponse' => $decryptedResponse,
            ]);
        } catch (ValidationException $ex) {
            return response()->json([
                'status' => false,
                'message' => 'Validation Error',
                'errors' => $ex->errors(),
            ], 422);
        } catch (\Exception $ex) {
            return response()->json([
                'status' => false,
                'message' => $ex->getMessage(),
            ], 500);
        }
    }

    // --------------------------------------------------------------

    // PRODUCTION LEVEL APIS
    public function billProcessProd(Request $request)
    {
        try {

            $biller = BharatConnectMdm::where('blr_id', $request->billerId)->first();

            if (! $biller) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid billerId',
                ], 404);
            }

            // Decode biller response
            $billerResponse = is_string($biller->biller_response)
                ? json_decode($biller->biller_response, true)
                : $biller->biller_response;

            // Fetch requirement
            $fetchRequirement = strtoupper($billerResponse['billerFetchRequiremet'] ?? 'MANDATORY');

            // Decide which API to call
            if (in_array($fetchRequirement, ['MANDATORY', 'OPTIONAL'])) {
                $apiType = 'billFetch';
                $response = $this->billFetchProd($request);
            } else {
                $apiType = 'billValidationApi';
                $response = $this->billValidationApiProd($request);
            }

            // Extract data + HTTP code from response
            $data = $response->getData(true);
            $statusCode = $response->getStatusCode();

            // Determine success based on biller logic
            $inner = $data['decryptedResponse'] ?? [];
            $responseCode = $inner['responseCode'] ?? null;

            // Biller success rule
            $isSuccess = ($responseCode === '000');

            // If biller returned success but statusCode not 200 → enforce 200
            if ($isSuccess && $statusCode !== 200) {
                $statusCode = 200;
            }

            // If biller returned failure but HTTP 200 → convert to 422
            if (! $isSuccess && $statusCode === 200) {
                $statusCode = 422;
            }

            return response()->json([
                'status' => $isSuccess,
                'billerId' => $request->billerId,
                'calledApi' => $apiType,
                'result' => $data,
            ], $statusCode);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // json current api (for bill fetch)
    public function billFetchProd(Request $request)
    {
        try {
            $credentials = CommonSecurityService::commonCredentials();
            $requestId = CommonSecurityService::generateRequestIdProd();

            // Base validation for customer fields
            $baseRules = [
                'billerId' => 'required|string|size:14',
                'customerMobile' => 'required|digits:10',
                'customerEmail' => 'nullable|email',
                'customerAdhaar' => 'nullable|digits:12',
                'customerPan' => 'nullable|string|max:10',
            ];

            $biller = BharatConnectMdm::where('blr_id', $request->billerId)->first();
            if (! $biller) {
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

            if (! empty($extraKeys)) {
                return response()->json([
                    'status' => false,
                    'message' => "Invalid input parameters for billerId {$request->billerId}",
                    'expectedParams' => $expectedParams,
                    'receivedExtra' => $extraKeys,
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

            if (! empty($mandatoryParams) && ! empty($missingParams)) {
                return response()->json([
                    'status' => false,
                    'message' => "Consumer input required for billerId {$request->billerId}",
                    'missingParams' => array_values($missingParams),
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
                        'paramValue' => is_numeric($value) ? ($value) : $value,
                    ];
                }
            }

            $savedData = BfBillFetchProd::create([
                'user_id' => auth()->id(),
                'blr_id' => $validatedData['billerId'],
                'request_id' => $requestId,
            ]);

            $payload = [
                'agentId' => $credentials['agent_id'],
                'billerAdhoc' => filter_var($billerResponse['billerAdhoc'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'agentDeviceInfo' => [
                    'ip' => $request->ip(),
                    'initChannel' => $credentials['payment_channel'],
                    'mac' => 'A1-B2-C3-D4-E5-F6',
                ],
                'customerInfo' => [
                    'customerMobile' => $validatedData['customerMobile'],
                    'customerEmail' => $validatedData['customerEmail'] ?? '',
                    'customerAdhaar' => $validatedData['customerAdhaar'] ?? '',
                    'customerPan' => $validatedData['customerPan'] ?? '',
                ],
                'billerId' => $validatedData['billerId'],
                'inputParams' => ['input' => $inputParams],
            ];

            $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);

            $encryptedRequest = CommonSecurityService::encryptTest($jsonPayload, $credentials['working_key']);

            $url = 'https://api.billavenue.com/billpay/extBillCntrl/billFetchRequest/json'   // PRODUCTION
                ."?accessCode={$credentials['access_code']}"
                ."&instituteId={$credentials['agent_institution_id']}"
                ."&requestId={$requestId}"
                ."&ver={$credentials['version']}"
                ."&encRequest={$encryptedRequest}";

            Log::channel('bill_fetch_prod')->info('Production Bill Fetch Url Request:', [
                'url' => $url,
                'request_id' => $requestId,
            ]);

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

            if (CommonSecurityService::isHex($response)) {
                $decryptedString = CommonSecurityService::decryptTest($response, $credentials['working_key']);
                $decryptedResponse = json_decode($decryptedString, true) ?? $decryptedString;
            } else {
                $decryptedResponse = $response;
            }

            if ($savedData) {
                $cleanResponse = $decryptedResponse['decryptedResponse'] ?? $decryptedResponse;

                $savedData->update([
                    'bill_fetch_response' => $cleanResponse,
                    'input_params' => ['inputParams' => $cleanResponse['inputParams'] ?? null],
                    'biller_response' => ['billerResponse' => $cleanResponse['billerResponse'] ?? null],
                    'additional_info' => ['additionalInfo' => $cleanResponse['additionalInfo'] ?? null],
                ]);
            }

            Log::info('Production Bill Fetch Decrypted Response:', ['response' => $decryptedResponse]);

            return response()->json([
                // 'status'      => true,
                // 'requestId'   => $requestId,
                // 'credentials' => $credentials,
                // 'validated'   => $validatedData,
                'decryptedResponse' => $decryptedResponse,
            ]);
        } catch (ValidationException $ex) {
            return response()->json([
                'status' => false,
                'message' => 'Validation Error',
                'errors' => $ex->errors(),
            ], 422);
        } catch (\Exception $ex) {
            return response()->json([
                'status' => false,
                'message' => $ex->getMessage(),
            ], 500);
        }
    }

    // json current api (for bill validation)
    public function billValidationApiProd(Request $request)
    {
        try {
            $credentials = CommonSecurityService::commonCredentials();
            $requestId = CommonSecurityService::generateRequestIdProd();

            // Base validation for customer fields
            $baseRules = [
                'billerId' => 'required|string|size:14',
                // 'customerMobile' => 'required|digits:10',
                // 'customerEmail'  => 'nullable|email',
                // 'customerAdhaar' => 'nullable|digits:12',
                // 'customerPan'    => 'nullable|string|max:10',
            ];

            $biller = BharatConnectMdm::where('blr_id', $request->billerId)->first();
            if (! $biller) {
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

            if (! empty($extraKeys)) {
                return response()->json([
                    'status' => false,
                    'message' => "Invalid input parameters for billerId {$request->billerId}",
                    'expectedParams' => $expectedParams,
                    'receivedExtra' => $extraKeys,
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

            if (! empty($mandatoryParams) && ! empty($missingParams)) {
                return response()->json([
                    'status' => false,
                    'message' => "Consumer input required for billerId {$request->billerId}",
                    'missingParams' => array_values($missingParams),
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
                        'paramValue' => is_numeric($value) ? ($value) : $value,
                    ];
                }
            }

            $savedData = BfBillFetchProd::create([
                'user_id' => auth()->id(),
                'blr_id' => $validatedData['billerId'],
                'request_id' => $requestId,
            ]);

            $payload = [
                'agentId' => $credentials['agent_id'],
                'billerAdhoc' => filter_var($billerResponse['billerAdhoc'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'agentDeviceInfo' => [
                    'ip' => $request->ip(),
                    'initChannel' => $credentials['payment_channel'],
                    'mac' => 'A1-B2-C3-D4-E5-F6',
                ],
                // "customerInfo" => [
                //     "customerMobile" => $validatedData['customerMobile'],
                //     "customerEmail" => $validatedData['customerEmail'] ?? '',
                //     "customerAdhaar" => $validatedData['customerAdhaar'] ?? '',
                //     "customerPan" => $validatedData['customerPan'] ?? '',
                // ],
                'billerId' => $validatedData['billerId'],
                'inputParams' => ['input' => $inputParams],
            ];

            $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);

            $encryptedRequest = CommonSecurityService::encryptTest($jsonPayload, $credentials['working_key']);

            $url = 'https://api.billavenue.com/billpay/extBillValCntrl/billValidationRequest/json'   // PRODUCTION
                ."?accessCode={$credentials['access_code']}"
                ."&instituteId={$credentials['agent_institution_id']}"
                ."&requestId={$requestId}"
                ."&ver={$credentials['version']}"
                ."&encRequest={$encryptedRequest}";

            Log::channel('bill_validation_prod')->info('Production Bill Validation Url Request', [
                'url' => $url,
                'request_id' => $requestId,
            ]);

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

            if (CommonSecurityService::isHex($response)) {
                $decryptedString = CommonSecurityService::decryptTest($response, $credentials['working_key']);
                $decryptedResponse = json_decode($decryptedString, true) ?? $decryptedString;
            } else {
                $decryptedResponse = $response;
            }

            if ($savedData) {
                $cleanResponse = $decryptedResponse['decryptedResponse'] ?? $decryptedResponse;

                $savedData->update([
                    'bill_fetch_response' => $cleanResponse,
                    'input_params' => ['inputParams' => $cleanResponse['inputParams'] ?? null],
                    'biller_response' => ['billerResponse' => $cleanResponse['billerResponse'] ?? null],
                    'additional_info' => ['additionalInfo' => $cleanResponse['additionalInfo'] ?? null],
                ]);
            }

            // dd($cleanResponse);
            Log::info('Production Bill Validation Decrypted Response:', ['response' => $decryptedResponse]);

            return response()->json([
                // 'status'      => true,
                // 'requestId'   => $requestId,
                // 'credentials' => $credentials,
                // 'validated'   => $validatedData,
                'decryptedResponse' => $decryptedResponse,
            ]);
        } catch (ValidationException $ex) {
            return response()->json([
                'status' => false,
                'message' => 'Validation Error',
                'errors' => $ex->errors(),
            ], 422);
        } catch (\Exception $ex) {
            return response()->json([
                'status' => false,
                'message' => $ex->getMessage(),
            ], 500);
        }
    }
}
