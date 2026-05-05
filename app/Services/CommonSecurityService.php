<?php

namespace App\Services;

class CommonSecurityService
{
    public static function commonCredentials1() //Staged UAT
    {
        return [
            'agent_institution_id' => 'SF12',    //Staged UAT
            'agent_institution_name' => 'Spay Fintech Pvt Ltd',
            'access_code' => 'AVQN45HX65IX97YMMJ',   //Staged UAT
            'working_key' => '75FC4C28834C28B8ED86C8F84D18D2B4', //Staged UAT
            'agent_id' => 'CC01CC01513515340681', //Staged UAT
            'payment_channel' => 'AGT',
            'version' => '1.0',
            'version2' => '2.0',
        ];
    }
    
    public static function commonCredentials() //production
    {
        return [
            'agent_institution_id' => 'RA16',   //production
            'agent_institution_name' => 'Spay Fintech Pvt Ltd',
            'access_code' => 'AVFI22GJ40OU84GYKG',  //production
            'working_key' => '8812E716B1CE8327F43C4895DC0E0DB8',    //production
            'agent_id' => 'CC01RA16AGTBAL101515',   //production
            'payment_channel' => 'AGT',
            'version' => '1.0',
            'version2' => '2.0',
        ];
    }
    
    //old generateRequestId method for generating RequestId
    // private function generateRequestId()
    // {
    //     $now = now();
    //     $yearDigit = substr($now->format('Y'), -1);
    //     $dayOfYear = str_pad($now->dayOfYear, 3, '0', STR_PAD_LEFT);
    //     $time = $now->format('Hi'); // 24-hour + minute
    //     $randomPart = strtoupper(bin2hex(random_bytes(14))); // 28 chars
    //     return substr($randomPart, 0, 27) . $yearDigit . $dayOfYear . $time;
    // }

    // new generateRequestId method for generating RequestId - 0 //staged uat generateRequestId
    public static function generateRequestId()
    {
        $now = now();

        // Julian date components
        $yearDigit = substr($now->format('Y'), -1);  // last digit of year
        $dayOfYear = str_pad($now->dayOfYear, 3, '0', STR_PAD_LEFT); // 001–366
        $time = $now->format('Hi'); // hhmm

        // Build suffix in required format
        $julianSuffix = $yearDigit . $dayOfYear . $time; // 8 chars

        // 23 random alphanumeric characters
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $randomPart = '';
        for ($i = 0; $i < 23; $i++) {
            $randomPart .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return 'SPAY' . $randomPart . $julianSuffix; // exactly 35 chars
    }
    
    // new generateRequestId method for generating RequestId - 0 //production generateRequestIdProd
    public static function generateRequestIdProd()
    {
        $now = now();
    
        // --- Julian date/time components ---
        $yearDigit = substr($now->format('Y'), -1); // last digit of year
        $dayOfYear = str_pad($now->dayOfYear, 3, '0', STR_PAD_LEFT); // 001–365
        $hour = $now->format('H'); // 24-hour format
        $minute = $now->format('i'); // minutes
    
        // Julian suffix format: YDDDhhmm
        $julianSuffix = $yearDigit . $dayOfYear . $hour . $minute;
    
        // --- Random alphanumeric prefix (27 characters) ---
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $randomPart = '';
        for ($i = 0; $i < 27; $i++) {
            $randomPart .= $characters[random_int(0, strlen($characters) - 1)];
        }
    
        // --- Combine parts ---
        $requestId = $randomPart . $julianSuffix;
    
        return $requestId; // total length = 27 + 8 = 35
    }

    //working
    public static function generatePaymentRefId()
    {
        $credentials = self::commonCredentials();
        $agentId = $credentials['agent_id'];

        return $agentId . date('YmdHis') . rand(1000, 9999);
    }
    
    // spay reference txn id
    public static function spayTransactionId()
    {
        return 'SPAY' 
            . now()->format('YmdHis')   // Timestamp: 20250128 123045
            . rand(11111111, 99999999); // 8-digit random number
    }

    public static function hextobin($hexString)
    {
        $length = strlen($hexString);
        $binString = "";
        $count = 0;
        while ($count < $length) {
            $subString = substr($hexString, $count, 2);
            //echo $subString;exit;
            $packedString = pack("H*", $subString);
            if ($count == 0) {
                $binString = $packedString;
            } else {
                $binString .= $packedString;
            }

            $count += 2;
        }
        return $binString;
    }

    public static function encryptTest($plainText, $key)
    {
        $key = self::hextobin(md5($key));
        $initVector = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
        $openMode = openssl_encrypt($plainText, "AES-128-CBC", $key, OPENSSL_RAW_DATA, $initVector);
        $encryptedText = bin2hex($openMode);
        return $encryptedText;
    }

    public static function hextobin_decrypt($hexString)
    {
        return pack("H*", $hexString);
    }

    public static function isHex($string)
    {
        return ctype_xdigit($string);
    }

    public static function decryptTest($encryptedText, $key)
    {
        $key = self::hextobin_decrypt(md5($key));
        $initVector = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
        $encryptedText = self::hextobin_decrypt($encryptedText);
        $decryptedText = openssl_decrypt($encryptedText, "AES-128-CBC", $key, OPENSSL_RAW_DATA, $initVector);
        return $decryptedText;
    }
}
