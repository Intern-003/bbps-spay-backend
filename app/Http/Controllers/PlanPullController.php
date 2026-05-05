<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CommonSecurityService;
use Illuminate\Support\Facades\Log;
use App\Models\PlanPullMdmTest;
use App\Models\PlanPullMdmProd;

class PlanPullController extends Controller
{
    public function planPull(Request $request){
        $rawContent = trim(file_get_contents('php://input'));
    
        if (empty($rawContent)) {
            return response()->json([
                'message' => 'Validation error',
                'errors'  => ['biller_id' => ['The biller id field is required.']],
            ], 422);
        }
    
        $billerIds = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $rawContent))));
        
        $credentials = CommonSecurityService::commonCredentials1();
        $requestId   = CommonSecurityService::generateRequestId();
        
        $payload = ["billerId" => $billerIds];
        
        $encryptedRequest = CommonSecurityService::encryptTest(json_encode($payload), $credentials['working_key']);
        // dd($encryptedRequest);
        
        $url = "https://stgapi.billavenue.com/billpay/extPlanMDM/planMdmRequest/json"   //UAT STAGED
        // $url = "https://api.billavenue.com/billpay/extPlanMDM/planMdmRequest/json"   //PRODUCTION
            . "?accessCode={$credentials['access_code']}"
            . "&instituteId={$credentials['agent_institution_id']}"
            . "&ver={$credentials['version']}"
            . "&requestId={$requestId}";
            
        Log::info('Plan Pull url request:', ['url' => $url]);
        
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
          CURLOPT_POSTFIELDS =>$encryptedRequest,
          CURLOPT_HTTPHEADER => array(
            'Content-Type: text/plain'
          ),
        ));

        $response = curl_exec($curl);
        $curlError = curl_error($curl);
        curl_close($curl);
        
        if ($curlError) {
            Log::error('BBPS API Error', ['error' => $curlError]);
            return [];
        }
    
        $decrypted = CommonSecurityService::isHex($response)
            ? CommonSecurityService::decryptTest($response, $credentials['working_key'])
            : $response;
            
        $decoded = json_decode($decrypted, true);
        
        // if (!empty($decoded['planDetails']) && is_array($decoded['planDetails'])) {
    
        //     foreach ($decoded['planDetails'] as $plan) {
    
        //         $billerId = $plan['billerId'] ?? null;
    
        //         if ($billerId) {
        //             PlanPullMdmTest::updateOrCreate(
        //                 ['plan_biller_id' => $billerId],
        //                 ['plan_biller_response' => $decoded]
        //             );
        //         }
        //     }
        // }

        return response()->json([
            "decoded" => $decoded
        ]);


    }
    
    public function planPullProd(Request $request){
        $rawContent = trim(file_get_contents('php://input'));
    
        if (empty($rawContent)) {
            return response()->json([
                'message' => 'Validation error',
                'errors'  => ['biller_id' => ['The biller id field is required.']],
            ], 422);
        }
    
        $billerIds = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $rawContent))));
        
        $credentials = CommonSecurityService::commonCredentials();
        $requestId   = CommonSecurityService::generateRequestIdProd();
        
        $payload = ["billerId" => $billerIds];
        
        $encryptedRequest = CommonSecurityService::encryptTest(json_encode($payload), $credentials['working_key']);
        // dd($encryptedRequest);
        
        // $url = "https://stgapi.billavenue.com/billpay/extPlanMDM/planMdmRequest/json"   //UAT STAGED
        $url = "https://api.billavenue.com/billpay/extPlanMDM/planMdmRequest/json"   //PRODUCTION
            . "?accessCode={$credentials['access_code']}"
            . "&instituteId={$credentials['agent_institution_id']}"
            . "&ver={$credentials['version']}"
            . "&requestId={$requestId}";

        Log::channel('plan_pull_prod')->info('Production Plan Pull url request: ', [
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
          CURLOPT_POSTFIELDS =>$encryptedRequest,
          CURLOPT_HTTPHEADER => array(
            'Content-Type: text/plain'
          ),
        ));

        $response = curl_exec($curl);
        $curlError = curl_error($curl);
        curl_close($curl);
        
        if ($curlError) {
            Log::error('BBPS API Error', ['error' => $curlError]);
            return [];
        }
    
        $decrypted = CommonSecurityService::isHex($response)
            ? CommonSecurityService::decryptTest($response, $credentials['working_key'])
            : $response;
            
        $decoded = json_decode($decrypted, true);
        
        // if (!empty($decoded['planDetails']) && is_array($decoded['planDetails'])) {
    
        //     foreach ($decoded['planDetails'] as $plan) {
    
        //         $billerId = $plan['billerId'] ?? null;
    
        //         if ($billerId) {
        //             PlanPullMdmProd::updateOrCreate(
        //                 ['plan_biller_id' => $billerId],
        //                 ['plan_biller_response' => $decoded]
        //             );
        //         }
        //     }
        // }

        return response()->json([
            "decoded" => $decoded
        ]);


    }

}
