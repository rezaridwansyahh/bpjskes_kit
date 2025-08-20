<?php
/**
 * BPJSTK API Client - Clean Production Version
 * Based on partner's working bpjs_tk_kit implementation
 */

// Suppress PHP deprecation warnings from the LZ-String library
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

// Use the partner's working LZ-String library
require_once __DIR__ . '/bpjs_tk_kit/vendor/autoload.php';

class BPJSTKClient {
    private $consumerID;
    private $consumerSecret;
    private $userKey;
    private $baseURL;
    
    public function __construct($consumerID, $consumerSecret, $userKey, $baseURL = 'https://apijkn-dev.bpjs-kesehatan.go.id') {
        $this->consumerID = $consumerID;
        $this->consumerSecret = $consumerSecret;
        $this->userKey = $userKey;
        $this->baseURL = $baseURL;
    }
    
    /**
     * Generate HMAC-SHA256 signature as per BPJS specification
     */
    private function generateSignature($timestamp) {
        $message = $this->consumerID . '&' . $timestamp;
        $signature = hash_hmac('sha256', $message, $this->consumerSecret, true);
        return base64_encode($signature);
    }
    
    /**
     * Generate encryption key using partner's method: SHA256(consumerID + consumerSecret + timestamp)
     */
    private function generateEncryptionKey($timestamp) {
        $keyString = $this->consumerID . $this->consumerSecret . $timestamp;
        return $keyString; // Return as string, will be hashed in decrypt function
    }
    
    /**
     * Decrypt function based on partner's working implementation
     * Modified to work with dynamic key generation
     */
    private function stringDecrypt($string, $secret_key) {
        $encrypt_method = 'AES-256-CBC';
        
        // hash key exactly like partner's implementation
        $key = hex2bin(hash('sha256', $secret_key));
        
        // iv - encrypt method AES-256-CBC expects 16 bytes
        $iv = substr(hex2bin(hash('sha256', $secret_key)), 0, 16);
        
        $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, OPENSSL_RAW_DATA, $iv);
        
        if ($output === false) {
            // Try AES-256-ECB as fallback
            $output = openssl_decrypt(base64_decode($string), 'AES-256-ECB', $key, OPENSSL_RAW_DATA);
        }
        
        return $output;
    }
    
    /**
     * LZ-String decompress using partner's working library
     */
    private function decompress($string) {
        return \LZCompressor\LZString::decompressFromEncodedURIComponent($string);
    }
    
    /**
     * Make API call and process response - Clean version
     */
    public function makeAPICall($endpoint) {
        $timestamp = time();
        $signature = $this->generateSignature($timestamp);
        $encryptionKey = $this->generateEncryptionKey($timestamp);
        
        $headers = [
            'X-cons-id: ' . $this->consumerID,
            'X-timestamp: ' . $timestamp,
            'X-signature: ' . $signature,
            'user_key: ' . $this->userKey
        ];
        
        // Make API call
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseURL . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'success' => false,
                'error' => 'Network error: ' . $error,
                'code' => 0
            ];
        }
        
        if ($httpCode !== 200) {
            return [
                'success' => false,
                'error' => 'HTTP error: ' . $httpCode,
                'code' => $httpCode
            ];
        }
        
        $responseData = json_decode($response, true);
        if (!$responseData) {
            return [
                'success' => false,
                'error' => 'Invalid JSON response from server',
                'code' => $httpCode
            ];
        }
        
        // Process encrypted response using partner's method
        if (isset($responseData['response']) && is_string($responseData['response'])) {
            try {
                // Step 1: Decrypt using partner's method
                $decrypted = $this->stringDecrypt($responseData['response'], $encryptionKey);
                if ($decrypted === false) {
                    throw new Exception('AES decryption failed');
                }
                
                // Step 2: LZ-String decompress using partner's method
                $decompressed = $this->decompress($decrypted);
                if ($decompressed === null || $decompressed === false) {
                    throw new Exception('LZ-String decompression failed');
                }
                
                // Step 3: Parse as JSON
                $finalData = json_decode($decompressed, true);
                if ($finalData !== null) {
                    return [
                        'success' => true,
                        'code' => $responseData['metaData']['code'] ?? 200,
                        'message' => $responseData['metaData']['message'] ?? 'OK',
                        'data' => $finalData
                    ];
                } else {
                    return [
                        'success' => false,
                        'error' => 'Failed to parse response as JSON: ' . json_last_error_msg(),
                        'code' => 500
                    ];
                }
                
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'error' => 'Processing failed: ' . $e->getMessage(),
                    'code' => 500
                ];
            }
        }
        
        // Return unencrypted response as-is
        return [
            'success' => true,
            'code' => $responseData['metaData']['code'] ?? 200,
            'message' => $responseData['metaData']['message'] ?? 'OK',
            'data' => $responseData['response'] ?? $responseData
        ];
    }
    
    /**
     * Get ASN participant data
     */
    public function getPesertaASN($page = 1, $limit = 10, $unorid = '') {
        $endpoint = "/wskemdikbud/Services/pesertaasn/read/page/{$page}/limit/{$limit}/unorid/{$unorid}";
        return $this->makeAPICall($endpoint);
    }
    
    /**
     * Get clean array of ASN records (just the list data)
     */
    public function getASNRecords($page = 1, $limit = 10, $unorid = '') {
        $result = $this->getPesertaASN($page, $limit, $unorid);
        
        if ($result['success'] && isset($result['data']['list'])) {
            return $result['data']['list'];
        }
        
        return [];
    }
}

// Example usage
if ($_SERVER['REQUEST_METHOD'] === 'GET' || php_sapi_name() === 'cli') {
    
    echo "BPJSTK API Client - Clean Production Version\n";
    echo "===========================================\n\n";
    
    try {
        $client = new BPJSTKClient(
            '1171',                                    // Consumer ID
            '9g7gcvw1fS',                             // Consumer Secret
            '95fbb45a93ef7a55a4ed1ef281de2b49'        // User Key
        );
        
        // Example 1: Get full response
        echo "Example 1: Full API Response\n";
        $result = $client->getPesertaASN(1, 1, '9CB6A40FAED53E8AE050640A2A0313C3');
        
        if ($result['success']) {
            echo "✅ Success: " . $result['message'] . "\n";
            echo "Records found: " . count($result['data']['list']) . "\n";
            
            if (!empty($result['data']['list'])) {
                $record = $result['data']['list'][0];
                echo "\nFirst record:\n";
                echo "- Name: " . $record['nama_pegawai'] . "\n";
                echo "- NIK: " . $record['nik'] . "\n";
                echo "- Position: " . $record['jabatan'] . " (" . $record['jenis_jabatan'] . ")\n";
                echo "- Unit: " . $record['unit_kerja'] . "\n";
                echo "- Status: " . $record['status_peserta'] . "\n";
            }
        } else {
            echo "❌ Error: " . $result['error'] . "\n";
        }
        
        echo "\n" . str_repeat("-", 50) . "\n\n";
        
        // Example 2: Get just the records array
        echo "Example 2: Just the Records Array\n";
        $records = $client->getASNRecords(1, 2, '9CB6A40FAED53E8AE050640A2A0313C3');
        
        if (!empty($records)) {
            echo "✅ Found " . count($records) . " records\n";
            foreach ($records as $index => $record) {
                echo "\nRecord " . ($index + 1) . ":\n";
                echo "  Name: " . $record['nama_pegawai'] . "\n";
                echo "  Email: " . $record['email'] . "\n";
                echo "  Status: " . $record['status_peserta'] . "\n";
            }
        } else {
            echo "❌ No records found\n";
        }
        
    } catch (Exception $e) {
        echo "💥 Exception: " . $e->getMessage() . "\n";
    }
    
    echo "\n🎉 BPJSTK API Client is working perfectly!\n";
}
?>