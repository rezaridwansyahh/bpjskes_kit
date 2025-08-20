<?php

require_once 'vendor/autoload.php';

// function decrypt
function stringDecrypt($string){
  $output = false;
  
  $encrypt_method = 'AES-256-CBC';
  $secret_key = 'xxxxxx'; //token

  // hash
  $key = hex2bin(hash('sha256', $secret_key));
  echo 'key = '.$key."\n\n";
  
  // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
  $iv = substr(hex2bin(hash('sha256', $secret_key)), 0, 16);
echo 'iv = '.$iv."\n\n";

  $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, OPENSSL_RAW_DATA, $iv);
  
  return $output;
}

// function lzstring decompress https://github.com/nullpunkt/lz-string-php
function decompress($string){
  
  return \LZCompressor\LZString::decompressFromEncodedURIComponent($string);

}


echo '<pre>';
$text = 'HLsloOm0uH8vvmWP99BiTyirPd1G0eEj9pIceIaBAkfpI9nezcILjs15k8cjTfJYnuu9dxh9EnsOrzyytUrxtbWVxQszkipdym4GXPMziRrIR9YyfgscJiVvp3K76vdQxNYqYoo8DtnJGII0GMJb0/Z9U67FW9YUKMNAtvz/bXijVTvE0YSgE2EHwyXolGHVYZYcJd2MjA/ywFU38hUg97uXf3ToIFnFh77Ae/8MsNqglAaL72eFyrwFEjIl0O1MVvBWjrRvwrf3gqTrGHQ2dWwl/oP2pjbqywAnIr421J48mmam8GVRe/x3PFg5esEtRZtA7a+gDRWD5Tmb3msoav1v/rhwrIlPWjFvN4iWH9E='; //encrypted text
echo 'text encrypt  = '.$text."\n\n";
$decrypted = stringDecrypt($text);
echo 'decrypted = '.$decrypted."\n\n";
echo '</pre>';

echo 'result = '. decompress($decrypted);

?>