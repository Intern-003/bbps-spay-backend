<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BillerInfos;
use App\Models\BillFetches;
use App\Models\ComplaintRegisterTrack;
use App\Models\ComplaintRegisterTrackProd;
use App\Services\CommonSecurityService;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SimpleXMLElement;

class ComplaintRegisterTrackController extends Controller
{
    //Testing
    public function complaintRegistertest(Request $request)
    {

        $credentials = CommonSecurityService::commonCredentials1();
        $requestId = CommonSecurityService::generateRequestId();
        // Backend-assigned agentId
        $agentId = $credentials['agent_id'];

        // --------------------- VALIDATION RULES ---------------------
        $rules = [
            'complaintType' => 'required|string|in:Service,Transaction',
            'complainDesc' => 'required|string|max:255',
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
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'responseCode' => '400',
                'responseReason' => 'Validation Error',
                'errors' => $validator->errors(),
            ], 400);
        }

        // --------------------- JSON PAYLOAD CREATION ---------------------
        $data = [
            "complaintType" => $request->complaintType,
            "participationType" => "",
            "agentId" => "",
            "billerId" => "",
            "servReason" => "",
            "complainDesc" => $request->complainDesc,
            "txnRefId" => $request->txnRefId ?? "",
            "complaintDisposition" => $request->complaintDisposition
        ];

        if ($request->complaintType === 'Transaction') {
            if (!empty($request->billerId)) {
                $data['billerId'] = $request->billerId;
            }
        }

        if ($request->complaintType === 'Service') {

            $data['participationType'] = $request->participationType;

            if ($request->participationType === 'AGENT') {
                $data['agentId'] = $agentId;
            }

            if ($request->participationType === 'BILLER') {
                $data['billerId'] = $request->billerId;
            }

            if (!empty($request->servReason)) {
                $data['servReason'] = $request->servReason;
            }
        }

        $merchant_data = json_encode($data, JSON_PRETTY_PRINT);

        // --------------------- ENCRYPT THE REQUEST ---------------------
        $encryptedRequest = CommonSecurityService::encryptTest($merchant_data, $credentials['working_key']);

        $url = "https://stgapi.billavenue.com/billpay/extComplaints/register/json"
            . "?accessCode={$credentials['access_code']}"
            . "&instituteId={$credentials['agent_institution_id']}"
            . "&requestId={$requestId}"
            . "&ver={$credentials['version2']}"
            . "&encRequest={$encryptedRequest}";
            
        Log::info('BillAvenue Complaint Register URL:', ['url' => $url]);

        // --------------------- CURL API CALL ---------------------
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_CUSTOMREQUEST => 'POST',
        ]);

        $response = curl_exec($curl);
        $curlError = curl_error($curl);

        curl_close($curl);

        if ($curlError) {
            return response()->json([
                'status' => 'failed',
                'message' => 'cURL error: ' . $curlError,
            ], 500);
        }

        // --------------------- DECRYPT RESPONSE ---------------------
        if (CommonSecurityService::isHex($response)) {
            $decryptedResponse = CommonSecurityService::decryptTest($response, $credentials['working_key']);
            Log::info('Complaint Register Decrypted response:', ['decrypted' => $decryptedResponse]);
        } else {
            $decryptedResponse = $response;
            Log::info('Complaint Register Decrypted response:', ['decrypted' => $decryptedResponse]);
        }

        // --------------------- PARSE JSON RESPONSE ---------------------
        $jsonResp = json_decode($decryptedResponse, true);

        if ($jsonResp === null && json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Invalid JSON response received',
                'raw' => $decryptedResponse,
            ], 500);
        }

        // Extract fields
        $complaintAssigned = $jsonResp['complaintAssigned'] ?? null;
        $registerComplaintId = $jsonResp['complaintId'] ?? null;
        $registerResponseCode = $jsonResp['complaintResponseCode'] ?? null;
        $registerResponseReason = $jsonResp['complaintResponseReason'] ?? null;

        // --------------------- SAVE TO DATABASE ---------------------
        try {

            if ($registerResponseCode === '000' && strtoupper($registerResponseReason) === 'SUCCESS') {
                // dd(auth()->id());
                ComplaintRegisterTrack::create([
                    'user_id'  => auth()->id(),
                    'complaint_type' => $request->complaintType,
                    'participation_type' => $request->participationType ?? null,
                    'biller_id' => $request->billerId ?? null,
                    'txn_ref_id' => $request->txnRefId ?? null,
                    'complaint_desc' => $request->complainDesc,
                    'serv_reason' => $request->servReason ?? null,
                    'complaint_disposition' => $request->complaintDisposition,
                    'complaint_assigned' => $complaintAssigned,
                    'register_complaint_id' => $registerComplaintId,
                    'register_response_reason' => $registerResponseReason,
                    'complaint_status' => "ASSIGNED",
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }

        // --------------------- RETURN FINAL RESPONSE ---------------------
        return response()->json($jsonResp, 200);
    }
    //production 
    public function complaintRegisterprod(Request $request)
    {
        // $credentials = CommonSecurityService::commonCredentials();
        // $requestId = CommonSecurityService::generateRequestIdProd();
        $credentials = CommonSecurityService::commonCredentials1();
        $requestId = CommonSecurityService::generateRequestId();

        // Backend-assigned agentId
        $agentId = $credentials['agent_id'];

        // --------------------- VALIDATION RULES ---------------------
        $rules = [
            'complaintType' => 'required|string|in:Service,Transaction',
            'complainDesc' => 'required|string|max:255',
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
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'responseCode' => '400',
                'responseReason' => 'Validation Error',
                'errors' => $validator->errors(),
            ], 400);
        }

        // --------------------- JSON PAYLOAD CREATION ---------------------
        $data = [
            "complaintType" => $request->complaintType,
            "participationType" => "",
            "agentId" => "",
            "billerId" => "",
            "servReason" => "",
            "complainDesc" => $request->complainDesc,
            "txnRefId" => $request->txnRefId ?? "",
            "complaintDisposition" => $request->complaintDisposition
        ];

        if ($request->complaintType === 'Transaction') {
            if (!empty($request->billerId)) {
                $data['billerId'] = $request->billerId;
            }
        }

        if ($request->complaintType === 'Service') {

            $data['participationType'] = $request->participationType;

            if ($request->participationType === 'AGENT') {
                $data['agentId'] = $agentId;
            }

            if ($request->participationType === 'BILLER') {
                $data['billerId'] = $request->billerId;
            }

            if (!empty($request->servReason)) {
                $data['servReason'] = $request->servReason;
            }
        }

        $merchant_data = json_encode($data, JSON_PRETTY_PRINT);

        // --------------------- ENCRYPT THE REQUEST ---------------------
        $encryptedRequest = CommonSecurityService::encryptTest($merchant_data, $credentials['working_key']);

        $url = "https://stgapi.billavenue.com/billpay/extComplaints/register/json"
            . "?accessCode={$credentials['access_code']}"
            . "&instituteId={$credentials['agent_institution_id']}"
            . "&requestId={$requestId}"
            . "&ver={$credentials['version2']}"
            . "&encRequest={$encryptedRequest}";

        Log::channel('complaint_register_prod')->info('BillAvenue Production Complaint Register URL: ', [
            'url' => $url,
            'request_id' => $requestId,
        ]);

        // --------------------- CURL API CALL ---------------------
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_CUSTOMREQUEST => 'POST',
        ]);

        $response = curl_exec($curl);
        $curlError = curl_error($curl);

        curl_close($curl);

        if ($curlError) {
            return response()->json([
                'status' => 'failed',
                'message' => 'cURL error: ' . $curlError,
            ], 500);
        }

        // --------------------- DECRYPT RESPONSE ---------------------
        if (CommonSecurityService::isHex($response)) {
            $decryptedResponse = CommonSecurityService::decryptTest($response, $credentials['working_key']);
            Log::info('Production Complaint Register Decrypted response:', ['decrypted' => $decryptedResponse]);
        } else {
            $decryptedResponse = $response;
            Log::info('Production Complaint Register Decrypted response:', ['decrypted' => $decryptedResponse]);
        }

        // --------------------- PARSE JSON RESPONSE ---------------------
        $jsonResp = json_decode($decryptedResponse, true);

        if ($jsonResp === null && json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Invalid JSON response received',
                'raw' => $decryptedResponse,
            ], 500);
        }

        // Extract fields
        $complaintAssigned = $jsonResp['complaintAssigned'] ?? null;
        $registerComplaintId = $jsonResp['complaintId'] ?? null;
        $registerResponseCode = $jsonResp['complaintResponseCode'] ?? null;
        $registerResponseReason = $jsonResp['complaintResponseReason'] ?? null;

        // --------------------- SAVE TO DATABASE ---------------------
        try {

            if ($registerResponseCode === '000' && strtoupper($registerResponseReason) === 'SUCCESS') {
                ComplaintRegisterTrackProd::create([
                    'user_id'  => auth()->id(),
                    'complaint_type' => $request->complaintType,
                    'participation_type' => $request->participationType ?? null,
                    'biller_id' => $request->billerId ?? null,
                    'txn_ref_id' => $request->txnRefId ?? null,
                    'complaint_desc' => $request->complainDesc,
                    'serv_reason' => $request->servReason ?? null,
                    'complaint_disposition' => $request->complaintDisposition,
                    'complaint_assigned' => $complaintAssigned,
                    'register_complaint_id' => $registerComplaintId,
                    'register_response_reason' => $registerResponseReason,
                    'complaint_status' => "ASSIGNED",
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }

        // --------------------- RETURN FINAL RESPONSE ---------------------
        return response()->json($jsonResp, 200);
    }
    //Testing
    public function complaintStatusTest(Request $request)
    {
        $credentials = CommonSecurityService::commonCredentials1();
        $requestId = CommonSecurityService::generateRequestId();

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

        $data = [
            "complaintType" => $request->complaintType,
            "complaintId" => $request->complaintId,
        ];

        $merchant_data = json_encode($data, JSON_PRETTY_PRINT);

        // dd($merchant_data);
        $encryptedRequest = CommonSecurityService::encryptTest($merchant_data, $credentials['working_key']);

        $url = "https://stgapi.billavenue.com/billpay/extComplaints/track/json"
            . "?accessCode={$credentials['access_code']}"
            . "&instituteId={$credentials['agent_institution_id']}"
            . "&requestId={$requestId}"
            . "&ver={$credentials['version2']}"
            . "&encRequest={$encryptedRequest}";
            
        Log::info('BillAvenue Complaint Status URL:', ['url' => $url]);

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
                'status' => 'failed',
                'message' => 'cURL error: ' . $curlError,
            ], 500);
        }

        if (CommonSecurityService::isHex($response)) {
            $decryptedResponse = CommonSecurityService::decryptTest($response, $credentials['working_key']);
            Log::info('Complaint Track Decrypted response:', ['decrypted' => $decryptedResponse]);
        } else {
            $decryptedResponse = $response;
            Log::info('Complaint Track Decrypted response:', ['decrypted' => $decryptedResponse]);
        }

        // Decode JSON response
        $json = json_decode($decryptedResponse, true);

        // If JSON failed
        if ($json === null && json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Invalid JSON response',
                'raw' => $decryptedResponse,
            ], 500);
        }

        // Extract fields safely
        $trackComplaintId = $json['complaintId'] ?? null;
        $complaintRemarks = $json['complaintRemarks'] ?? null;
        $trackResponseReason = $json['complaintResponseReason'] ?? ($json['responseReason'] ?? null);
        $complaintStatus = $json['complaintStatus'] ?? null;

        if ($trackComplaintId) {

            // Find DB record
            $complaint = ComplaintRegisterTrack::where('register_complaint_id', $trackComplaintId)->first();

            if ($complaint) {
                $complaint->update([
                    'track_complaint_id' => $trackComplaintId,
                    'complaint_remarks' => $complaintRemarks,
                    'track_response_reason' => $trackResponseReason,
                    'complaint_status' => $complaintStatus,
                ]);
            }
        }

        // Return JSON response
        return response()->json($json, 200);


    }
    //production 
    public function complaintStatusProd(Request $request)
    {
        // $credentials = CommonSecurityService::commonCredentials();
        // $requestId = CommonSecurityService::generateRequestIdProd();
        $credentials = CommonSecurityService::commonCredentials1();
        $requestId = CommonSecurityService::generateRequestId();

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

        $data = [
            "complaintType" => $request->complaintType,
            "complaintId" => $request->complaintId,
        ];

        $merchant_data = json_encode($data, JSON_PRETTY_PRINT);

        // dd($merchant_data);
        $encryptedRequest = CommonSecurityService::encryptTest($merchant_data, $credentials['working_key']);

        $url = "https://stgapi.billavenue.com/billpay/extComplaints/track/json"
            . "?accessCode={$credentials['access_code']}"
            . "&instituteId={$credentials['agent_institution_id']}"
            . "&requestId={$requestId}"
            . "&ver={$credentials['version2']}"
            . "&encRequest={$encryptedRequest}";

        Log::channel('complaint_track_prod')->info('BillAvenue Production Complaint Status URL: ', [
            'url' => $url,
            'request_id' => $requestId,
        ]);

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
                'status' => 'failed',
                'message' => 'cURL error: ' . $curlError,
            ], 500);
        }

        if (CommonSecurityService::isHex($response)) {
            $decryptedResponse = CommonSecurityService::decryptTest($response, $credentials['working_key']);
            Log::info('Production Complaint Track Decrypted response:', ['decrypted' => $decryptedResponse]);
        } else {
            $decryptedResponse = $response;
            Log::info('Production Complaint Track Decrypted response:', ['decrypted' => $decryptedResponse]);
        }

        // Decode JSON response
        $json = json_decode($decryptedResponse, true);

        // If JSON failed
        if ($json === null && json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Invalid JSON response',
                'raw' => $decryptedResponse,
            ], 500);
        }

        // Extract fields safely
        $trackComplaintId = $json['complaintId'] ?? null;
        $complaintRemarks = $json['complaintRemarks'] ?? null;
        $trackResponseReason = $json['complaintResponseReason'] ?? ($json['responseReason'] ?? null);
        $complaintStatus = $json['complaintStatus'] ?? null;

        if ($trackComplaintId) {

            // Find DB record
            $complaint = ComplaintRegisterTrackProd::where('register_complaint_id', $trackComplaintId)->first();

            if ($complaint) {
                $complaint->update([
                    'track_complaint_id' => $trackComplaintId,
                    'complaint_remarks' => $complaintRemarks,
                    'track_response_reason' => $trackResponseReason,
                    'complaint_status' => $complaintStatus,
                ]);
            }
        }

        // Return JSON response
        return response()->json($json, 200);


    }

    public function allComplaintsdata(Request $request)
    {

        $data = ComplaintRegisterTrack::all();
        return response()->json($data);

    }
    
    public function allComplaintsdataProd(Request $request)
    {

        $data = ComplaintRegisterTrackProd::all();
        return response()->json($data);

    }
}
