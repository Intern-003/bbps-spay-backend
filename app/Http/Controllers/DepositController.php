<?php

namespace App\Http\Controllers;

use App\Services\CommonSecurityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DepositController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    // production
    public function depositEnquiryProd(Request $request)
    {
        $credentials = CommonSecurityService::commonCredentials();
        $requestId = CommonSecurityService::generateRequestIdProd();

        // Validation rules
        $rules = [
            'fromDate' => 'required|date',
            'toDate' => 'required|date|after_or_equal:fromDate',
            'transType' => 'nullable|string',
            'agentId' => 'required|string|max:255',
        ];

        // Validate request
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'responseCode' => '400',
                'responseReason' => 'Validation Error',
                'errors' => $validator->errors(),
            ], 400);
        }

        if ($validator->fails()) {
            return response()->json([
                'responseCode' => '400',
                'responseCode' => '400',
            ]);
        }

        // Prepare data payload
        $data = [
            'fromDate' => $request->fromDate ?? '2024-10-31',
            'toDate' => $request->toDate ?? '2024-11-01',
            'transType' => $request->transType ?? '',
            'agentId' => $request->agentId ?? 'CC01RA16AGTBAL101515',
        ];

        $merchant_data = json_encode($data, JSON_PRETTY_PRINT);
        $encryptedRequest = CommonSecurityService::encryptTest($merchant_data, $credentials['working_key']);

        $url = 'https://api.billavenue.com/billpay/enquireDeposit/fetchDetails/json'
            ."?accessCode={$credentials['access_code']}"
            ."&instituteId={$credentials['agent_institution_id']}"
            ."&requestId={$requestId}"
            ."&ver={$credentials['version']}"
            ."&encRequest={$encryptedRequest}";

        Log::channel('deposit_enquiry_prod')->info('Production Deposit Enquiry Url Request:', [
            'url' => $url,
            'request_id' => $requestId,
        ]);

        // CURL Request
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
            return response()->json([
                'status' => 'failed',
                'message' => 'cURL error: '.$curlError,
            ], 500);
        }

        // Decrypt response
        if (CommonSecurityService::isHex($response)) {
            $decryptedResponse = CommonSecurityService::decryptTest($response, $credentials['working_key']);
        } else {
            $decryptedResponse = $response;
        }

        // Decode JSON
        $json = json_decode($decryptedResponse, true);

        if ($json === null && json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Invalid JSON response',
                'raw' => $decryptedResponse,
            ], 500);
        }

        // ✅ FINAL RETURN RESPONSE HERE
        return response()->json([
            'status' => 'success',
            'requestId' => $requestId,
            'data' => $json,
        ], 200);
    }

    // Testing
    public function depositEnquiryTest(Request $request)
    {
        $credentials = CommonSecurityService::commonCredentials1();
        $requestId = CommonSecurityService::generateRequestId();

        // Validation rules
        $rules = [
            'fromDate' => 'required|date',
            'toDate' => 'required|date|after_or_equal:fromDate',
            'transType' => 'nullable|string',
            'agentId' => 'required|string|max:255',
        ];

        // Validate request
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'responseCode' => '400',
                'responseReason' => 'Validation Error',
                'errors' => $validator->errors(),
            ], 400);
        }

        // Prepare data payload
        $data = [
            'fromDate' => $request->fromDate ?? '2024-10-31',
            'toDate' => $request->toDate ?? '2024-11-01',
            'transType' => $request->transType ?? '',
            'agentId' => $request->agentId ?? 'CC01RA16AGTBAL101515',
        ];

        $merchant_data = json_encode($data, JSON_PRETTY_PRINT);
        $encryptedRequest = CommonSecurityService::encryptTest($merchant_data, $credentials['working_key']);

        $url = 'https://stgapi.billavenue.com/billpay/enquireDeposit/fetchDetails/json'
            ."?accessCode={$credentials['access_code']}"
            ."&instituteId={$credentials['agent_institution_id']}"
            ."&requestId={$requestId}"
            ."&ver={$credentials['version']}"
            ."&encRequest={$encryptedRequest}";

        // CURL Request
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
            return response()->json([
                'status' => 'failed',
                'message' => 'cURL error: '.$curlError,
            ], 500);
        }

        // Decrypt response
        if (CommonSecurityService::isHex($response)) {
            $decryptedResponse = CommonSecurityService::decryptTest($response, $credentials['working_key']);
        } else {
            $decryptedResponse = $response;
        }

        // Decode JSON
        $json = json_decode($decryptedResponse, true);

        if ($json === null && json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Invalid JSON response',
                'raw' => $decryptedResponse,
            ], 500);
        }

        // ✅ FINAL RETURN RESPONSE HERE
        return response()->json([
            'status' => 'success',
            'requestId' => $requestId,
            'data' => $json,
        ], 200);
    }
}
