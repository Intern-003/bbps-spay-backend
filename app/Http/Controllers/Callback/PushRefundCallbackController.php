<?php

namespace App\Http\Controllers\Callback;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use App\Models\BillerPushRefund;
use App\Services\CommonSecurityService;

class PushRefundCallbackController extends Controller
{
    public function pushRefundNotification(Request $post)
    {
        $credentials = CommonSecurityService::commonCredentials();

        // Store the incoming request payload in a variable
        $responseJson = $post->getContent();
        
        Log::info('Push Refund Callback Received', [
            'pushRefundNotification payload' => $responseJson
        ]);
        
        // $encryptedRequest = CommonSecurityService::encryptTest(
        //     json_encode($responseJson, JSON_UNESCAPED_SLASHES),
        //     $credentials['working_key']
        // );
        
        if (CommonSecurityService::isHex($responseJson)) {
            $decryptedResponse = CommonSecurityService::decryptTest($responseJson, $credentials['working_key']);
            // dd("if: " . $decryptedResponse);
        } else {
            $decryptedResponse = $responseJson;
            // dd("else: " . $decryptedResponse);
        }

        $decoded = json_decode($decryptedResponse, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            Log::info('Push Refund Callback JSON decoded successfully', [
                'decoded_response' => $decoded
            ]);
        
            $decryptedResponse = $decoded;
        } else {
            Log::warning('Push Refund Callback JSON decode failed', [
                'error'   => json_last_error_msg(),
                'payload' => $decryptedResponse
            ]);
        }

        // Save callback data
        BillerPushRefund::create([
            'user_id'       => auth()->id(),
            'response_json' => $decoded,
        ]);
    
        return response()->json([
            'status'  => true,
            'message' => 'Callback stored successfully',
            'response'  => $decoded
        ]);
    }

}
