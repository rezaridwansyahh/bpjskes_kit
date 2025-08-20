<?php
/**
 * BPJSTK API Client - Usage Example
 * 
 * This example shows how to use the BPJSTK API client to make calls
 * and handle the encrypted/compressed responses.
 */

require_once 'BPJSTK_complete.php';

echo "BPJSTK API Client - Usage Example\n";
echo "=================================\n\n";

try {
    // Initialize the client with your credentials
    $client = new BPJSTKClient(
        '1171',                                    // Consumer ID (Development)
        '9g7gcvw1fS',                             // Consumer Secret (Development)
        '95fbb45a93ef7a55a4ed1ef281de2b49'        // User Key
    );
    
    echo "1. Making API call to get ASN participant data...\n";
    
    // Method 1: Clean response (recommended for most use cases)
    $result = $client->getASNData(1, 10, '9CB6A40FAED53E8AE050640A2A0313C3');
    
    if ($result['success']) {
        echo "✓ Success! API returned data:\n";
        echo "  Code: " . $result['code'] . "\n";
        echo "  Message: " . $result['message'] . "\n";
        echo "  Processed with: " . ($result['processed_with'] ?? 'N/A') . "\n";
        
        if (isset($result['data']['list'])) {
            echo "  Found " . count($result['data']['list']) . " ASN records\n";
            
            // Display first record
            $firstRecord = $result['data']['list'][0];
            echo "\n  First record:\n";
            echo "    NIK: " . ($firstRecord['nik'] ?? 'N/A') . "\n";
            echo "    Nama: " . ($firstRecord['nama_pegawai'] ?? 'N/A') . "\n";
            echo "    Unit Kerja: " . ($firstRecord['unit_kerja'] ?? 'N/A') . "\n";
        }
    } else {
        echo "⚠ API call completed but data needs manual processing:\n";
        echo "  Error: " . $result['error'] . "\n";
        echo "  Code: " . $result['code'] . "\n";
        
        if (isset($result['decrypted_data'])) {
            echo "\n  Decrypted data available for manual LZ-String decompression:\n";
            echo "  Length: " . strlen($result['decrypted_data']) . " characters\n";
            echo "  Data: " . substr($result['decrypted_data'], 0, 100) . "...\n";
            echo "\n  Suggestion: " . ($result['suggested_action'] ?? 'Try external tools') . "\n";
        }
    }
    
    echo "\n" . str_repeat("-", 50) . "\n\n";
    
    echo "2. Alternative: Get detailed response with debug info...\n";
    
    // Method 2: Detailed response with debug output (for troubleshooting)
    $detailedResult = $client->getPesertaASN(1, 3, '9CB6A40FAED53E8AE050640A2A0313C3', true);
    
    echo "\nDetailed result structure:\n";
    echo "- Success: " . ($detailedResult['success'] ? 'Yes' : 'No') . "\n";
    echo "- Code: " . $detailedResult['code'] . "\n";
    echo "- Available fields: " . implode(', ', array_keys($detailedResult)) . "\n";
    
} catch (Exception $e) {
    echo "❌ Exception occurred: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "API Usage Summary:\n";
echo "- Use getASNData() for clean, simple responses\n";
echo "- Use getPesertaASN() with debug=true for troubleshooting\n";
echo "- AES decryption is working correctly\n"; 
echo "- LZ-String decompression may need external tools\n";
echo "- Decrypted data is always provided for manual processing\n";
?>