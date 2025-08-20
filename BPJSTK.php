<?php
/**
 * BPJS API Client Function
 * 
 * This function handles API calls to BPJS endpoints using the LZString compression
 * Compatible with PHP 7.2
 */

// Include the LZString compression library
require_once 'vendor/autoload.php';

/**
 * Call BPJS API endpoint with proper authentication and data formatting
 * 
 * @param string $endpoint The API endpoint URL
 * @param string $consumerID The consumer ID for authentication
 * @param string $consumerSecret The consumer secret key for authentication
 * @param array $data The data to be sent in the request (optional)
 * @param string $method HTTP method (GET, POST, PUT, DELETE)
 * @return array Response data and status
 */
function callBPJSApi($endpoint, $consumerID, $consumerSecret, $data = [], $method = 'POST') {
    // Timestamps for authentication
    $timestamp = time();
    $encodedTimestamp = urlencode($timestamp);
    
    // Generate signature
    $signature = generateSignature($consumerID, $consumerSecret, $timestamp);
    
    // Headers required for BPJS API
    $headers = [
        'Content-Type: application/x-www-form-urlencoded',
        'X-cons-id: ' . $consumerID,
        'X-timestamp: ' . $encodedTimestamp,
        'X-signature: ' . $signature
    ];
    
    // Compress data using LZString if needed
    if (!empty($data) && ($method == 'POST' || $method == 'PUT')) {
        $jsonData = json_encode($data);
        $compressedData = \LZCompressor\LZString::compressToEncodedURIComponent($jsonData);
        $payload = 'data=' . $compressedData;
    } else {
        $payload = '';
    }
    
    // Initialize cURL
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Note: In production, you should verify SSL
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Note: In production, you should verify SSL
    
    // Set HTTP method and payload if needed
    switch ($method) {
        case 'POST':
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($payload)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            }
            break;
        case 'PUT':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if (!empty($payload)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            }
            break;
        case 'DELETE':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            break;
        default: // GET
            if (!empty($data)) {
                $queryString = http_build_query($data);
                curl_setopt($ch, CURLOPT_URL, $endpoint . '?' . $queryString);
            }
            break;
    }
    
    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    // Close cURL
    curl_close($ch);
    
    // Check for errors
    if ($error) {
        echo "error founded ! \n";
        return [
            'success' => false,
            'status_code' => $httpCode,
            'message' => 'cURL Error: ' . $error,
            'data' => null
        ];
    }
    
    // Process response
    $responseData = json_decode($response, true);
    echo $response."\n";
    
    // Check if response is encrypted/compressed
    if (isset($responseData['response']) && is_string($responseData['response'])) {
        try {
            $decompressed = \LZCompressor\LZString::decompressFromEncodedURIComponent($responseData['response']);
            $responseData['response'] = json_decode($decompressed, true);
            echo "decompress success \n";
        } catch (\Exception $e) {
            // If decompression fails, keep original response
            echo "decompressor failed !!!! \n";
        }
    }
    
    return [
        'success' => ($httpCode >= 200 && $httpCode < 300),
        'status_code' => $httpCode,
        'message' => ($httpCode >= 200 && $httpCode < 300) ? 'Success' : 'Error',
        'data' => $responseData
    ];
}

/**
 * Generate HMAC signature for BPJS API authentication
 * 
 * @param string $consumerID Consumer ID
 * @param string $consumerSecret Consumer Secret
 * @param int $timestamp Current timestamp
 * @return string The calculated signature
 */
function generateSignature($consumerID, $consumerSecret, $timestamp) {
    $message = $consumerID . '&' . $timestamp;
    $signature = hash_hmac('sha256', $message, $consumerSecret, true);
    return base64_encode($signature);
}

/**
 * Get Peserta ASN data from Kemdikbud service
 * 
 * @param int $page Page number for pagination
 * @param int $limit Number of records per page
 * @param string $unorid UNORId parameter for filtering
 * @return array Response data and status
 */
function getPesertaASN($page = 1, $limit = 10, $unorid = '') {
    // API configuration
    $consumerID = '1171'; // Replace with your consumer ID
    $consumerSecret = '9g7gcvw1fS'; // Replace with your consumer secret
    
    // Construct the endpoint URL with path parameters
    $endpoint = "https://apijkn-dev.bpjs-kesehatan.go.id/wskemdikbud/Services/pesertaasn/read/page/{$page}/limit/{$limit}/unorid/{$unorid}";
    
    // Call the API
    return callBPJSApi($endpoint, $consumerID, $consumerSecret, [], 'GET');
}

/**
 * Example usage for submitting data to BPJS Kemdikbud service
 * 
 * @param array $asnData The ASN data to submit
 * @return array Response data and status
 */
function submitASNData($asnData) {
    // API configuration
    $consumerID = 'YOUR_CONSUMER_ID'; // Replace with your consumer ID
    $consumerSecret = 'YOUR_CONSUMER_SECRET'; // Replace with your consumer secret
    $endpoint = 'https://apijkn-dev.bpjs-kesehatan.go.id/wskemdikbud/Services/pesertaasn/create';
    
    // Call the API
    return callBPJSApi($endpoint, $consumerID, $consumerSecret, $asnData, 'POST');
}

// Example of how to use the functions
/*
// Example 1: Get peserta ASN data with pagination


// Example 2: Submit ASN data
$asnData = [
    'nik' => '1234567890123456',
    'nama' => 'NAMA PESERTA',
    'tglLahir' => '1990-01-01',
    // Add other required fields based on API documentation
];
$submitResult = submitASNData($asnData);
*/

$result = getPesertaASN(1, 10, '9CB6A40FAED53E8AE050640A2A0313C3');
if ($result['success']) {
    echo "ASN participant data retrieved successfully!";
    print_r($result['data']);
    var_dump($result['data']['response']);
} else {
    echo "Error: " . $result['message'];
}