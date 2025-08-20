<?php
/**
 * Simple LZ-String decoder for BPJSTK
 * Based on the LZ-String algorithm
 */

class SimpleLZString {
    
    private static $keyStrUriSafe = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+-=";
    
    public static function decompressFromEncodedURIComponent($input) {
        if ($input == null) return "";
        if ($input == "") return null;
        
        $input = str_replace(' ', '+', $input);
        return self::_decompress(strlen($input), 32, function($index) use ($input) {
            return self::getBaseValue(self::$keyStrUriSafe, $input[$index]);
        });
    }
    
    private static function getBaseValue($alphabet, $character) {
        return strpos($alphabet, $character);
    }
    
    private static function _decompress($length, $resetValue, $getNextValue) {
        $dictionary = [];
        $next = null;
        $enlargeIn = 4;
        $dictSize = 4;
        $numBits = 3;
        $entry = "";
        $result = [];
        $i = 0;
        $w = "";
        $bits = 0;
        $maxpower = 2;
        $power = 1;
        $c = "";
        
        $data = [
            'val' => $getNextValue(0),
            'position' => $resetValue,
            'index' => 1
        ];
        
        for ($i = 0; $i < 3; $i++) {
            $dictionary[$i] = chr($i);
        }
        
        $bits = 0;
        $maxpower = 4;
        $power = 1;
        
        while ($power != $maxpower) {
            $resb = $data['val'] & $data['position'];
            $data['position'] >>= 1;
            
            if ($data['position'] == 0) {
                $data['position'] = $resetValue;
                $data['val'] = $getNextValue($data['index']++);
            }
            
            $bits |= ($resb > 0 ? 1 : 0) * $power;
            $power <<= 1;
        }
        
        switch ($next = $bits) {
            case 0:
                $bits = 0;
                $maxpower = 256;
                $power = 1;
                
                while ($power != $maxpower) {
                    $resb = $data['val'] & $data['position'];
                    $data['position'] >>= 1;
                    
                    if ($data['position'] == 0) {
                        $data['position'] = $resetValue;
                        $data['val'] = $getNextValue($data['index']++);
                    }
                    
                    $bits |= ($resb > 0 ? 1 : 0) * $power;
                    $power <<= 1;
                }
                
                $c = chr($bits);
                break;
                
            case 1:
                $bits = 0;
                $maxpower = 65536;
                $power = 1;
                
                while ($power != $maxpower) {
                    $resb = $data['val'] & $data['position'];
                    $data['position'] >>= 1;
                    
                    if ($data['position'] == 0) {
                        $data['position'] = $resetValue;
                        $data['val'] = $getNextValue($data['index']++);
                    }
                    
                    $bits |= ($resb > 0 ? 1 : 0) * $power;
                    $power <<= 1;
                }
                
                $c = chr($bits);
                break;
                
            case 2:
                return "";
        }
        
        $dictionary[3] = $c;
        $w = $c;
        $result[] = $c;
        
        while (true) {
            if ($data['index'] > $length) {
                return "";
            }
            
            $bits = 0;
            $maxpower = pow(2, $numBits);
            $power = 1;
            
            while ($power != $maxpower) {
                $resb = $data['val'] & $data['position'];
                $data['position'] >>= 1;
                
                if ($data['position'] == 0) {
                    $data['position'] = $resetValue;
                    $data['val'] = $getNextValue($data['index']++);
                }
                
                $bits |= ($resb > 0 ? 1 : 0) * $power;
                $power <<= 1;
            }
            
            switch ($c = $bits) {
                case 0:
                    $bits = 0;
                    $maxpower = 256;
                    $power = 1;
                    
                    while ($power != $maxpower) {
                        $resb = $data['val'] & $data['position'];
                        $data['position'] >>= 1;
                        
                        if ($data['position'] == 0) {
                            $data['position'] = $resetValue;
                            $data['val'] = $getNextValue($data['index']++);
                        }
                        
                        $bits |= ($resb > 0 ? 1 : 0) * $power;
                        $power <<= 1;
                    }
                    
                    $dictionary[$dictSize++] = chr($bits);
                    $c = $dictSize - 1;
                    $enlargeIn--;
                    break;
                    
                case 1:
                    $bits = 0;
                    $maxpower = 65536;
                    $power = 1;
                    
                    while ($power != $maxpower) {
                        $resb = $data['val'] & $data['position'];
                        $data['position'] >>= 1;
                        
                        if ($data['position'] == 0) {
                            $data['position'] = $resetValue;
                            $data['val'] = $getNextValue($data['index']++);
                        }
                        
                        $bits |= ($resb > 0 ? 1 : 0) * $power;
                        $power <<= 1;
                    }
                    
                    $dictionary[$dictSize++] = chr($bits);
                    $c = $dictSize - 1;
                    $enlargeIn--;
                    break;
                    
                case 2:
                    return implode('', $result);
            }
            
            if ($enlargeIn == 0) {
                $enlargeIn = pow(2, $numBits);
                $numBits++;
            }
            
            if (isset($dictionary[$c])) {
                $entry = $dictionary[$c];
            } else {
                if ($c === $dictSize) {
                    $entry = $w . $w[0];
                } else {
                    return null;
                }
            }
            
            $result[] = $entry;
            
            $dictionary[$dictSize++] = $w . $entry[0];
            $enlargeIn--;
            
            $w = $entry;
            
            if ($enlargeIn == 0) {
                $enlargeIn = pow(2, $numBits);
                $numBits++;
            }
        }
    }
}

// Test the LZ-String decoder with a sample
$testData = "UA7CBrBIDMBWAjLgFQmlkkgA0IKAhgLa0D6ADgKYDmtA7rRFgEEABACEAqiSEAFAErkiVEDDb0WtGExi0UHLmCZhaACwgAnLAGUZ";
echo "Testing LZ-String decoder...\n";
echo "Input: " . substr($testData, 0, 50) . "...\n";

try {
    $decoded = SimpleLZString::decompressFromEncodedURIComponent($testData);
    echo "Output: " . substr($decoded, 0, 100) . "...\n";
    
    // Try to parse as JSON
    $json = json_decode($decoded, true);
    if ($json) {
        echo "✓ Successfully decoded to JSON\n";
        print_r($json);
    } else {
        echo "Decoded but not JSON: " . substr($decoded, 0, 200) . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}