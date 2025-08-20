<?php
/**
 * BPJSTK API Client - Complete Implementation with LZ-String Support
 * Uses nullpunkt/lz-string-php for proper decompression
 */

require_once 'vendor/autoload.php';
use LZCompressor\LZString;

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
     * Generate encryption key: SHA256(consumerID + consumerSecret + timestamp)
     */
    private function generateEncryptionKey($timestamp) {
        $keyString = $this->consumerID . $this->consumerSecret . $timestamp;
        return hash('sha256', $keyString, true);
    }
    
    /**
     * Decrypt AES-256 encrypted response
     */
    private function decryptResponse($encryptedData, $key) {
        $data = base64_decode($encryptedData);
        if ($data === false) {
            throw new Exception('Base64 decode failed');
        }
        
        // Try AES-256-ECB first (no IV needed)
        $decrypted = openssl_decrypt($data, 'AES-256-ECB', $key, OPENSSL_RAW_DATA);
        
        if ($decrypted === false) {
            // Try AES-256-CBC with IV
            if (strlen($data) > 16) {
                $iv = substr($data, 0, 16);
                $encrypted = substr($data, 16);
                $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            }
        }
        
        if ($decrypted === false) {
            throw new Exception('AES decryption failed');
        }
        
        return $decrypted;
    }
    
    /**
     * Decompress LZ-String data using proper library with fallback handling
     */
    private function decompressLZString($input) {
        if (empty($input)) return '';
        
        try {
            // Try all available LZ-String decompression methods
            $methods = [
                'decompressFromEncodedURIComponent',
                'decompressFromUTF16', 
                'decompressFromBase64',
                'decompress'
            ];
            
            foreach ($methods as $method) {
                if (method_exists('LZCompressor\LZString', $method)) {
                    $result = LZString::$method($input);
                    
                    // Check if we got a meaningful result
                    if ($result !== null && $result !== false && is_string($result) && strlen($result) > 5) {
                        // Verify it's valid JSON or at least looks like data
                        $json = json_decode($result, true);
                        if ($json !== null) {
                            return $result; // Success! Return the decompressed JSON
                        }
                        
                        // If not JSON but substantial content, might still be valid
                        if (strlen($result) > 50) {
                            return $result;
                        }
                    }
                }
            }
            
            // If all LZ-String methods fail, the data might already be decompressed
            // or in a different format. Let's check if it's already JSON
            $json = json_decode($input, true);
            if ($json !== null) {
                return $input; // It's already valid JSON
            }
            
            // Try base64 decode as another fallback
            $base64Decoded = base64_decode($input, true);
            if ($base64Decoded !== false) {
                $json = json_decode($base64Decoded, true);
                if ($json !== null) {
                    return $base64Decoded;
                }
            }
            
            // Return input as-is if nothing works
            return $input;
            
        } catch (Exception $e) {
            // If decompression fails, return the input as-is
            return $input;
        }
    }
    
    /**
     * Make API request to BPJSTK endpoint and return clean JSON response
     */
    public function makeRequest($endpoint, $method = 'GET', $data = [], $debug = false) {
        $timestamp = time();
        $signature = $this->generateSignature($timestamp);
        
        $headers = [
            'X-cons-id: ' . $this->consumerID,
            'X-timestamp: ' . $timestamp,
            'X-signature: ' . $signature,
            'user_key: ' . $this->userKey
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseURL . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30
        ]);
        
        if ($method === 'POST' && !empty($data)) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            $headers[] = 'Content-Type: application/json';
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'success' => false,
                'error' => 'cURL Error: ' . $error,
                'code' => 0
            ];
        }
        
        if ($httpCode !== 200) {
            return [
                'success' => false,
                'error' => 'HTTP Error: ' . $httpCode,
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
        
        // Process encrypted response
        if (isset($responseData['response']) && is_string($responseData['response'])) {
            try {
                if ($debug) {
                    echo "Processing encrypted response...\n";
                    echo "Timestamp: {$timestamp}\n";
                    echo "Encrypted data length: " . strlen($responseData['response']) . " characters\n";
                }
                
                // Step 1: Decrypt AES
                $encryptionKey = $this->generateEncryptionKey($timestamp);
                $decrypted = $this->decryptResponse($responseData['response'], $encryptionKey);
                
                if ($debug) {
                    echo "✓ AES decryption successful (" . strlen($decrypted) . " bytes)\n";
                }
                
                // Step 2: LZ-String decompression
                if ($debug) {
                    echo "Decrypted data (first 100 chars): " . substr($decrypted, 0, 100) . "\n";
                    echo "Decrypted data (last 50 chars): " . substr($decrypted, -50) . "\n";
                }
                
                $decompressed = $this->decompressLZString($decrypted);
                
                if ($debug) {
                    echo "✓ LZ-String decompression: " . strlen($decompressed) . " chars\n";
                    if ($decompressed !== $decrypted) {
                        echo "✓ Data was decompressed successfully\n";
                        echo "Decompressed (first 200 chars): " . substr($decompressed, 0, 200) . "\n";
                    } else {
                        echo "⚠ Data unchanged - treating as already decompressed\n";
                    }
                }
                
                // Step 3: Parse JSON - try multiple approaches
                $finalData = null;
                $processedWith = '';
                
                // Try 1: Parse decompressed data as JSON
                if (strlen($decompressed) > 5) {
                    $finalData = json_decode($decompressed, true);
                    if ($finalData !== null) {
                        $processedWith = 'decompressed data';
                        if ($debug) echo "✓ JSON parsing successful with decompressed data\n";
                    }
                }
                
                // Try 2: If decompression failed or didn't give JSON, try original decrypted data
                if ($finalData === null) {
                    $finalData = json_decode($decrypted, true);
                    if ($finalData !== null) {
                        $processedWith = 'original decrypted data';
                        if ($debug) echo "✓ JSON parsing successful with original decrypted data\n";
                    }
                }
                
                // Try 3: Clean the data and try again
                if ($finalData === null) {
                    $dataToClean = strlen($decompressed) > 5 ? $decompressed : $decrypted;
                    $cleanData = trim($dataToClean);
                    $cleanData = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $cleanData);
                    $finalData = json_decode($cleanData, true);
                    if ($finalData !== null) {
                        $processedWith = 'cleaned data';
                        if ($debug) echo "✓ JSON parsing successful with cleaned data\n";
                    }
                }
                
                if ($finalData !== null) {
                    return [
                        'success' => true,
                        'code' => $responseData['metaData']['code'] ?? 200,
                        'message' => $responseData['metaData']['message'] ?? 'OK',
                        'data' => $finalData,
                        'processed_with' => $processedWith
                    ];
                } else {
                    if ($debug) {
                        echo "⚠ All JSON parsing attempts failed\n";
                        echo "Final JSON error: " . json_last_error_msg() . "\n";
                    }
                    
                    // Return the best available data for manual processing
                    $bestData = strlen($decompressed) > 5 ? $decompressed : $decrypted;
                    
                    return [
                        'success' => false,
                        'error' => 'Could not parse decrypted data as JSON. Data may need manual LZ-String decompression.',
                        'code' => $responseData['metaData']['code'] ?? 500,
                        'encrypted_data' => $responseData['response'],
                        'decrypted_data' => $decrypted,
                        'decompression_attempt' => $decompressed,
                        'suggested_action' => 'Try external LZ-String decompression tool or check if the data format has changed'
                    ];
                }
                
            } catch (Exception $e) {
                if ($debug) {
                    echo "✗ Processing failed: " . $e->getMessage() . "\n";
                }
                
                return [
                    'success' => false,
                    'error' => 'Decryption/Decompression failed: ' . $e->getMessage(),
                    'code' => $responseData['metaData']['code'] ?? 500
                ];
            }
        }
        
        // If response is not encrypted, return as-is
        return [
            'success' => true,
            'code' => $responseData['metaData']['code'] ?? 200,
            'message' => $responseData['metaData']['message'] ?? 'OK',
            'data' => $responseData['response'] ?? $responseData
        ];
    }
    
    /**
     * Get ASN participant data with pagination
     */
    public function getPesertaASN($page = 1, $limit = 10, $unorid = '', $debug = false) {
        $endpoint = "/wskemdikbud/Services/pesertaasn/read/page/{$page}/limit/{$limit}/unorid/{$unorid}";
        return $this->makeRequest($endpoint, 'GET', [], $debug);
    }
    
    /**
     * Get clean JSON response for ASN participant data
     * Returns only the data array on success, or error info on failure
     */
    public function getASNData($page = 1, $limit = 10, $unorid = '') {
        $result = $this->getPesertaASN($page, $limit, $unorid, false);
        
        if ($result['success']) {
            return [
                'success' => true,
                'data' => $result['data'],
                'code' => $result['code'],
                'message' => $result['message']
            ];
        } else {
            return [
                'success' => false,
                'error' => $result['error'],
                'code' => $result['code']
            ];
        }
    }
    
    /**
     * Helper function to analyze the decrypted data
     */
    public function analyzeDecryptedData($data) {
        echo "\n--- DATA ANALYSIS ---\n";
        echo "Data type: " . gettype($data) . "\n";
        echo "Data length: " . strlen($data) . " characters\n";
        echo "First 100 characters: " . substr($data, 0, 100) . "\n";
        echo "Last 100 characters: " . substr($data, -100) . "\n";
        
        // Check if it looks like LZ-String compressed data
        if (preg_match('/^[A-Za-z0-9+\/=\-_]+$/', $data)) {
            echo "Format: Appears to be base64/LZ-String encoded\n";
        } else {
            echo "Format: Contains non-base64 characters\n";
        }
        
        // Try to detect JSON patterns
        if (strpos($data, '{') !== false || strpos($data, '[') !== false) {
            echo "Content: May contain JSON data\n";
        }
    }
}

/**
 * Example usage - uncomment to test
 */
function testBPJSTKAPI() {
    echo "BPJSTK API Client - Test\n";
    echo "========================\n\n";

    try {
        $client = new BPJSTKClient(
            '1171',                                    // Consumer ID
            '9g7gcvw1fS',                             // Consumer Secret  
            '95fbb45a93ef7a55a4ed1ef281de2b49'        // User Key
        );
        
        echo "Making API call...\n";
        $result = $client->getPesertaASN(1, 10, '9CB6A40FAED53E8AE050640A2A0313C3', true);
        
        echo "\n--- API RESPONSE ---\n";
        echo "Success: " . ($result['success'] ? 'Yes' : 'No') . "\n";
        echo "Code: " . $result['code'] . "\n";
        echo "Message: " . ($result['message'] ?? $result['error'] ?? 'N/A') . "\n";
        
        if ($result['success'] && isset($result['data'])) {
            echo "\n--- DATA ---\n";
            if (isset($result['data']['list'])) {
                echo "Found " . count($result['data']['list']) . " ASN records\n";
                
                foreach ($result['data']['list'] as $index => $record) {
                    echo "\nRecord " . ($index + 1) . ":\n";
                    echo "  NIK: " . ($record['nik'] ?? 'N/A') . "\n";
                    echo "  Nama: " . ($record['nama_pegawai'] ?? 'N/A') . "\n";
                    echo "  Unit Kerja: " . ($record['unit_kerja'] ?? 'N/A') . "\n";
                    echo "  Status: " . ($record['status_peserta'] ?? 'N/A') . "\n";
                }
            } else {
                echo "Data structure:\n";
                print_r($result['data']);
            }
        } else {
            echo "Error: " . ($result['error'] ?? 'Unknown error') . "\n";
            if (isset($result['raw_data'])) {
                echo "Raw data (first 200 chars): " . substr($result['raw_data'], 0, 200) . "...\n";
            }
        }
        
    } catch (Exception $e) {
        echo "Exception: " . $e->getMessage() . "\n";
    }
}

// Uncomment the line below to run the test
// testBPJSTKAPI();