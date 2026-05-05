<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BillerInformation;
use App\Services\CommonSecurityService;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SimpleXMLElement;
use App\Models\BharatConnectMdm;
use App\Models\BharatConnectMdmTest;

class BillerInformationController extends Controller
{
    // STAGED LEVEL APIS
    public function getBillers($category) {
        $billers = BharatConnectMdmTest //staged-db
        ::select('blr_id', 'blr_name')
                                    ->where('blr_category_name', $category)
                                    ->orderBy('blr_name', 'asc')
                                    ->get();
            
        return response()->json($billers);    
    }
    
    public function syncBillerInfo(Request $request)
    {
        // Fetch up to 5 biller IDs where biller_response is NULL
        $billerIds = BharatConnectMdm::query()
                        ->whereNull('biller_response')
                        ->limit(2000)
                        ->pluck('blr_id')
                        ->toArray();

        // If no such billers found → return validation error
        if (empty($billerIds)) {
            return response()->json([
                'message' => 'Validation error',
                'errors'  => ['biller_id' => ['No biller IDs available with empty biller_response.']],
            ], 422);
        }

        // ✅ 1️⃣ First check which IDs already exist in DB
        $existingRecords = BharatConnectMdm::whereIn('blr_id', $billerIds)
            ->get()
            ->keyBy('blr_id');

        $dbResponses = [];
        $idsNotFound = [];

        foreach ($billerIds as $id) {
            if (isset($existingRecords[$id]) && !empty($existingRecords[$id]->biller_response)) {
                // ✅ Use cached response
                $dbResponses[] = $existingRecords[$id]->biller_response;
            } else {
                // ❌ Missing IDs → API call required
                $idsNotFound[] = $id;
            }
        }

        // ✅ 2️⃣ If all found in DB → return directly (no API call)
        if (empty($idsNotFound)) {
            return response()->json([
                "biller" => $dbResponses
            ]);
        }

        // ✅ 3️⃣ Call API ONLY for missing IDs
        $apiResponses = $this->fetchAndUpdateBillersFromAPI($idsNotFound);

        // ✅ 4️⃣ Merge both
        return response()->json([
            "biller" => array_merge($dbResponses, $apiResponses)
        ]);
    }
    
    //get biller ids from body raw content to store biller_response - current working api
    public function billerInfo(Request $request)
    {
        $rawContent = trim(file_get_contents('php://input'));
        if (empty($rawContent)) {
            return response()->json([
                'message' => 'Validation error',
                'errors'  => ['biller_id' => ['The biller id field is required.']],
            ], 422);
        }
    
        $billerIds = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $rawContent))));

        // ✅ 1️⃣ First check which IDs already exist in DB
        $existingRecords = BharatConnectMdmTest::whereIn('blr_id', $billerIds)
            ->get()
            ->keyBy('blr_id');

        $dbResponses = [];
        $idsNotFound = [];
    
        foreach ($billerIds as $id) {
            if (isset($existingRecords[$id]) && !empty($existingRecords[$id]->biller_response)) {
                // ✅ Use cached response
                $dbResponses[] = $existingRecords[$id]->biller_response;
            } else {
                // ❌ Missing IDs → API call required
                $idsNotFound[] = $id;
            }
        }
    
        // ✅ 2️⃣ If all found in DB → return directly (no API call)
        if (empty($idsNotFound)) {
            return response()->json([
                "biller" => $dbResponses
            ]);
        }
    
        // ✅ 3️⃣ Call API ONLY for missing IDs
        $apiResponses = $this->fetchAndUpdateBillersFromAPI($idsNotFound);
    
        // ✅ 4️⃣ Merge both
        return response()->json([
            "biller" => array_merge($dbResponses, $apiResponses)
        ]);
    }

    private function fetchAndUpdateBillersFromAPI(array $idsNotFound)
    {
        $credentials = CommonSecurityService::commonCredentials1();
        $requestId   = CommonSecurityService::generateRequestId();

        $payload = ["billerId" => $idsNotFound];

        $encryptedRequest = CommonSecurityService::encryptTest(json_encode($payload), $credentials['working_key']);

        $url = "https://stgapi.billavenue.com/billpay/extMdmCntrl/mdmRequestNew/json"    //staged biller info api
            . "?accessCode={$credentials['access_code']}"
            . "&instituteId={$credentials['agent_institution_id']}"
            . "&ver={$credentials['version']}"
            . "&requestId={$requestId}";

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $encryptedRequest,
            CURLOPT_HTTPHEADER => ['Content-Type: text/plain'],
        ]);
    
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
    
        // ✅ Update cache database
        if (!empty($decoded['biller']) && is_array($decoded['biller'])) {
            foreach ($decoded['biller'] as $biller) {
                if (!isset($biller['billerId'])) continue;
    
                BharatConnectMdmTest::updateOrCreate(
                    ['blr_id' => $biller['billerId']],
                    [
                        'blr_name' => $biller['billerName'] ?? null,
                        'blr_alias_name' => $biller['billerAliasName'] ?? null,
                        'blr_category_name' => $biller['billerCategory'] ?? null,
                        'blr_coverage' => $biller['billerCoverage'] ?? null,
                        'biller_response' => $biller,
                    ]
                );
            }
        }
    
        return $decoded['biller'] ?? [];
    }
    
    public function getBillerNameAndCategory()
    {
        try {
            // Fetch all billers with required fields
            $billers = BharatConnectMdm::select(
                    'blr_id',
                    'blr_name',
                    'blr_alias_name',
                    'blr_category_name',
                    'blr_coverage'
                )
                ->orderBy('blr_name', 'ASC')
                ->get();
    
            return response()->json([
                'status' => true,
                'message' => 'Biller list fetched successfully',
                'data' => $billers
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong',
                'error'   => $e->getMessage()
            ], 500);
        }
}

    
    
    
    
    // --------------------------------------------------------------
    
    

    
    
    // PRODUCTION LEVEL APIS
    
    
    public function getBillersProd($category) {
        $billers = BharatConnectMdm  //production-db
        ::select('blr_id', 'blr_name')
                                    ->where('blr_category_name', $category)
                                    ->orderBy('blr_name', 'asc')
                                    ->get();
        return response()->json($billers);    
    }
    
    
    public function syncBillerInfoProd(Request $request)
    {
        // Fetch up to 5 biller IDs where biller_response is NULL
        $billerIds = BharatConnectMdm::query()
                        ->whereNull('biller_response')
                        ->limit(2000)
                        ->pluck('blr_id')
                        ->toArray();

        // If no such billers found → return validation error
        if (empty($billerIds)) {
            return response()->json([
                'message' => 'Validation error',
                'errors'  => ['biller_id' => ['No biller IDs available with empty biller_response.']],
            ], 422);
        }

        // ✅ 1️⃣ First check which IDs already exist in DB
        $existingRecords = BharatConnectMdm::whereIn('blr_id', $billerIds)
            ->get()
            ->keyBy('blr_id');

        $dbResponses = [];
        $idsNotFound = [];

        foreach ($billerIds as $id) {
            if (isset($existingRecords[$id]) && !empty($existingRecords[$id]->biller_response)) {
                // ✅ Use cached response
                $dbResponses[] = $existingRecords[$id]->biller_response;
            } else {
                // ❌ Missing IDs → API call required
                $idsNotFound[] = $id;
            }
        }

        // ✅ 2️⃣ If all found in DB → return directly (no API call)
        if (empty($idsNotFound)) {
            return response()->json([
                "biller" => $dbResponses
            ]);
        }

        // ✅ 3️⃣ Call API ONLY for missing IDs
        $apiResponses = $this->fetchAndUpdateBillersFromAPI($idsNotFound);

        // ✅ 4️⃣ Merge both
        return response()->json([
            "biller" => array_merge($dbResponses, $apiResponses)
        ]);
    }
    
    //get biller ids from body raw content to store biller_response - current working api
    public function billerInfoProd(Request $request)
    {
        $rawContent = trim(file_get_contents('php://input'));
    
        if (empty($rawContent)) {
            return response()->json([
                'message' => 'Validation error',
                'errors'  => ['biller_id' => ['The biller id field is required.']],
            ], 422);
        }
    
        $billerIds = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $rawContent))));
    
        // ✅ 1️⃣ First check which IDs already exist in DB
        $existingRecords = BharatConnectMdm::whereIn('blr_id', $billerIds)
            ->get()
            ->keyBy('blr_id');
    
        $dbResponses = [];
        $idsNotFound = [];
    
        foreach ($billerIds as $id) {
            if (isset($existingRecords[$id]) && !empty($existingRecords[$id]->biller_response)) {
                // ✅ Use cached response
                $dbResponses[] = $existingRecords[$id]->biller_response;
            } else {
                // ❌ Missing IDs → API call required
                $idsNotFound[] = $id;
            }
        }
    
        // ✅ 2️⃣ If all found in DB → return directly (no API call)
        if (empty($idsNotFound)) {
            return response()->json([
                "biller" => $dbResponses
            ]);
        }
    
        // ✅ 3️⃣ Call API ONLY for missing IDs
        $apiResponses = $this->fetchAndUpdateBillersFromAPIProd($idsNotFound);
    
        // ✅ 4️⃣ Merge both
        return response()->json([
            "biller" => array_merge($dbResponses, $apiResponses)
        ]);
    }

    private function fetchAndUpdateBillersFromAPIProd(array $idsNotFound)
    {
        $credentials = CommonSecurityService::commonCredentials();
        $requestId   = CommonSecurityService::generateRequestIdProd();
    
        $payload = ["billerId" => $idsNotFound];
        $encryptedRequest = CommonSecurityService::encryptTest(json_encode($payload), $credentials['working_key']);
    
        $url = "https://api.billavenue.com/billpay/extMdmCntrl/mdmRequestNew/json"  //production biller info api
            . "?accessCode={$credentials['access_code']}"
            . "&instituteId={$credentials['agent_institution_id']}"
            . "&ver={$credentials['version']}"
            . "&requestId={$requestId}";
    
        Log::channel('biller_info_prod')->info('Biller Info Production URL', [
            'url' => $url,
            'request_id' => $requestId,
        ]);
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $encryptedRequest,
            CURLOPT_HTTPHEADER => ['Content-Type: text/plain'],
        ]);
    
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
    
        // ✅ Update cache database
        if (!empty($decoded['biller']) && is_array($decoded['biller'])) {
            foreach ($decoded['biller'] as $biller) {
                if (!isset($biller['billerId'])) continue;
    
                BharatConnectMdm::updateOrCreate(
                    ['blr_id' => $biller['billerId']],
                    [
                        'blr_name' => $biller['billerName'] ?? null,
                        'blr_alias_name' => $biller['billerAliasName'] ?? null,
                        'blr_category_name' => $biller['billerCategory'] ?? null,
                        'blr_coverage' => $biller['billerCoverage'] ?? null,
                        'biller_response' => $biller,
                    ]
                );
            }
        }
    
        return $decoded['biller'] ?? [];
    }


}
