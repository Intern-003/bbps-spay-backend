<?php

namespace App\Http\Controllers;

use App\Models\BpBillPayment;
use App\Services\CommonSecurityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TxnStatusController extends Controller
{
    // Testing
    public function transactionStatusTest(Request $request)
    {
        // 1️⃣ Get user input
        $userTxnRefId = $request->input('txnRefID');
        $userRequestId = $request->input('request_id');
        $userMobileNo = $request->input('mobile_no');
        $userFromDate = $request->input('fromDate', date('Y-m-d', strtotime('-7 days'))); // default 7 days ago
        $userToDate = $request->input('toDate', date('Y-m-d')); // default today

        // 2️⃣ Load credentials
        $credentials = CommonSecurityService::commonCredentials1();

        // 3️⃣ Prepare to collect all responses
        $allResponses = [];

        // 4️⃣ Determine source of records: user input or database
        $records = collect();

        if ($userTxnRefId || $userRequestId || $userMobileNo) {
            // User provided input, make it a single record
            $records->push((object) [
                'txnRefID' => $userTxnRefId,
                'request_id' => $userRequestId,
                'mobile_no' => $userMobileNo,
                'fromDate' => $userFromDate,
                'toDate' => $userToDate,
                'blr_id' => 'USER_INPUT', // dummy ID for tracking
            ]);
        } else {
            // No user input, fallback to database
            $records = BpBillPayment::select('blr_id', 'request_id', 'txnRefID', 'mobile_no')->get();
            if ($records->isEmpty()) {
                return response()->json([
                    'message' => 'Validation error',
                    'errors' => ['tracking' => ['No records found in database and no user input provided.']],
                ], 422);
            }
        }

        // 5️⃣ Loop through each record
        foreach ($records as $payment) {
            $urlRequestId = CommonSecurityService::generateRequestId(); // unique per request

            // Determine tracking type
            if (! empty($payment->txnRefID)) {
                $trackType = 'TRANS_REF_ID';
                $trackValue = $payment->txnRefID;
                $merchant_data_array = [
                    'trackingType' => $trackType,
                    'trackingValue' => $trackValue,
                ];
            } elseif (! empty($payment->request_id)) {
                $trackType = 'REQUEST_ID';
                $trackValue = $payment->request_id;
                $merchant_data_array = [
                    'trackingType' => $trackType,
                    'trackingValue' => $trackValue,
                ];
            } elseif (! empty($payment->mobile_no)) {
                $trackType = 'MOBILE_NO';
                $trackValue = $payment->mobile_no;
                $merchant_data_array = [
                    'trackingType' => $trackType,
                    'trackingValue' => $trackValue,
                    'fromDate' => $payment->fromDate ?? $userFromDate,
                    'toDate' => $payment->toDate ?? $userToDate,
                ];
            } else {
                $allResponses[$payment->blr_id ?? 'UNKNOWN'] = ['error' => 'No valid tracking ID available.'];

                continue;
            }

            // Build JSON payload
            $merchant_data = json_encode($merchant_data_array, JSON_UNESCAPED_SLASHES);

            // Encrypt payload
            $encRequest = CommonSecurityService::encryptTest($merchant_data, $credentials['working_key']);

            // Build URL
            $params = [
                'instituteId' => $credentials['agent_institution_id'],
                'requestId' => $urlRequestId,
                'ver' => $credentials['version'],
                'encRequest' => $encRequest,
                'accessCode' => $credentials['access_code'],
            ];
            $url = 'https://stgapi.billavenue.com/billpay/transactionStatus/fetchInfo/json?'.http_build_query($params);

            Log::info('BillAvenue Transaction Status URL:', ['url' => $url]);

            // Send cURL request
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CUSTOMREQUEST => 'POST',
            ]);
            $response = curl_exec($curl);
            $curlError = curl_error($curl);
            curl_close($curl);

            if ($curlError) {
                $allResponses[$trackValue] = ['error' => $curlError];

                continue;
            }

            // Decrypt response if needed
            $decryptedResponse = CommonSecurityService::isHex(trim($response))
                ? CommonSecurityService::decryptTest(trim($response), $credentials['working_key'])
                : $response;

            // Decode JSON
            $json = json_decode($decryptedResponse, true);

            // Log responses
            Log::info('TxnStatus API Raw Response:', ['trackValue' => $trackValue, 'response' => $decryptedResponse]);
            Log::info('TxnStatus API Parsed JSON:', ['trackValue' => $trackValue, 'json' => $json]);

            // Collect the response
            $allResponses[$trackValue] = $json;
        }

        // 6️⃣ Return all responses together
        return response()->json([
            'status' => 'success',
            'responses' => $allResponses,
        ]);
    }

    // production
    public function transactionStatusProd(Request $request)
    {
        // dd("transactionStatusProd");
        // 1️⃣ Get user input
        $userTxnRefId = $request->input('txnRefID');
        $userRequestId = $request->input('request_id');
        $userMobileNo = $request->input('mobile_no');
        $userFromDate = $request->input('fromDate', date('Y-m-d', strtotime('-7 days'))); // default 7 days ago
        $userToDate = $request->input('toDate', date('Y-m-d')); // default today

        // 2️⃣ Load credentials
        $credentials = CommonSecurityService::commonCredentials();

        // 3️⃣ Prepare to collect all responses
        $allResponses = [];

        // 4️⃣ Determine source of records: user input or database
        $records = collect();

        if ($userTxnRefId || $userRequestId || $userMobileNo) {
            // User provided input, make it a single record
            $records->push((object) [
                'txnRefID' => $userTxnRefId,
                'request_id' => $userRequestId,
                'mobile_no' => $userMobileNo,
                'fromDate' => $userFromDate,
                'toDate' => $userToDate,
                'blr_id' => 'USER_INPUT', // dummy ID for tracking
            ]);
        } else {
            // No user input, fallback to database
            $records = BpBillPayment::select('blr_id', 'request_id', 'txnRefID', 'mobile_no')->get();
            if ($records->isEmpty()) {
                return response()->json([
                    'message' => 'Validation error',
                    'errors' => ['tracking' => ['No records found in database and no user input provided.']],
                ], 422);
            }
        }

        // 5️⃣ Loop through each record
        foreach ($records as $payment) {
            $urlRequestId = CommonSecurityService::generateRequestId(); // unique per request

            // Determine tracking type
            if (! empty($payment->txnRefID)) {
                $trackType = 'TRANS_REF_ID';
                $trackValue = $payment->txnRefID;
                $merchant_data_array = [
                    'trackingType' => $trackType,
                    'trackingValue' => $trackValue,
                ];
            } elseif (! empty($payment->request_id)) {
                $trackType = 'REQUEST_ID';
                $trackValue = $payment->request_id;
                $merchant_data_array = [
                    'trackingType' => $trackType,
                    'trackingValue' => $trackValue,
                ];
            } elseif (! empty($payment->mobile_no)) {
                $trackType = 'MOBILE_NO';
                $trackValue = $payment->mobile_no;
                $merchant_data_array = [
                    'trackingType' => $trackType,
                    'trackingValue' => $trackValue,
                    'fromDate' => $payment->fromDate ?? $userFromDate,
                    'toDate' => $payment->toDate ?? $userToDate,
                ];
            } else {
                $allResponses[$payment->blr_id ?? 'UNKNOWN'] = ['error' => 'No valid tracking ID available.'];

                continue;
            }

            // Build JSON payload
            $merchant_data = json_encode($merchant_data_array, JSON_UNESCAPED_SLASHES);

            // Encrypt payload
            $encRequest = CommonSecurityService::encryptTest($merchant_data, $credentials['working_key']);

            // Build URL
            $params = [
                'instituteId' => $credentials['agent_institution_id'],
                'requestId' => $urlRequestId,
                'ver' => $credentials['version'],
                'encRequest' => $encRequest,
                'accessCode' => $credentials['access_code'],
            ];
            $url = 'https://api.billavenue.com/billpay/transactionStatus/fetchInfo/json?'.http_build_query($params);

            Log::channel('transaction_status_prod')->info('BillAvenue Production Transaction Status URL: ', [
                'url' => $url,
                'request_id' => $urlRequestId,
            ]);

            // Send cURL request
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CUSTOMREQUEST => 'POST',
            ]);
            $response = curl_exec($curl);
            $curlError = curl_error($curl);
            curl_close($curl);

            if ($curlError) {
                $allResponses[$trackValue] = ['error' => $curlError];

                continue;
            }

            // Decrypt response if needed
            $decryptedResponse = CommonSecurityService::isHex(trim($response))
                ? CommonSecurityService::decryptTest(trim($response), $credentials['working_key'])
                : $response;

            // Decode JSON
            $json = json_decode($decryptedResponse, true);

            // Log responses
            Log::info('Production TxnStatus API Raw Response:', ['trackValue' => $trackValue, 'response' => $decryptedResponse]);
            Log::info('Production TxnStatus API Parsed JSON:', ['trackValue' => $trackValue, 'json' => $json]);

            // Collect the response
            $allResponses[$trackValue] = $json;
        }

        // 6️⃣ Return all responses together
        return response()->json([
            'status' => 'success',
            'responses' => $allResponses,
        ]);
    }
}
