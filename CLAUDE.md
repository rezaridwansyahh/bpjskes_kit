# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a BPJSTK (BPJS Tenaga Kerja) API client implementation for integrating with BPJS Kesehatan's ASN (Aparatur Sipil Negara) participant data services. The project handles encrypted API responses that require AES-256 decryption followed by LZ-String decompression.

## Key Architecture Components

### Response Processing Flow
1. **API Request**: Send authenticated request with HMAC-SHA256 signature
2. **AES Decryption**: Decrypt response using AES-256 (ECB or CBC mode)  
3. **LZ-String Decompression**: Decompress decrypted data using LZ-String
4. **JSON Parsing**: Parse final JSON response

### Authentication Headers
- `X-cons-id`: Consumer ID (1171 for dev)
- `X-timestamp`: Unix timestamp  
- `X-signature`: HMAC-SHA256(consumerID&timestamp, consumerSecret)
- `user_key`: Static user key for authorization

### Encryption Details
- **Signature**: HMAC-SHA256 with message format `consumerID&timestamp`
- **Encryption Key**: SHA256(consumerID + consumerSecret + timestamp)  
- **AES Mode**: Both ECB and CBC variants supported
- **Compression**: LZ-String with `decompressFromEncodedURIComponent()`

## Common Development Tasks

### Running PHP Scripts
```bash
php filename.php
```

### Testing API Integration
```bash
php BPJSTK_final_working.php
```

### Installing Dependencies
```bash
composer install
```

### Testing Different Decompression Methods
```bash
php test_decompression.php
```

## File Structure

- `BPJSTK_final_working.php` - Main working implementation with complete flow
- `bpjs_tk_kit/decrypt.php` - Basic decryption utility functions
- `docs.txt` - API specification and sample responses
- `composer.json` - Dependencies (3 LZ-String PHP implementations)
- `test_*.php` - Various testing and debugging scripts
- `vendor/` - Composer dependencies for LZ-String libraries

## Key Classes and Methods

### BPJSTKClient (BPJSTK_final_working.php)
- `makeRequest($endpoint, $method, $data)` - Main API request handler
- `getPesertaASN($page, $limit, $unorid)` - Get ASN participant data
- `generateSignature($timestamp)` - Generate HMAC-SHA256 signature
- `generateEncryptionKey($timestamp)` - Create AES encryption key
- `decryptResponse($encryptedData, $key)` - AES decryption with fallback modes
- `decompressLZString($input)` - LZ-String decompression

## Dependencies

The project includes three LZ-String PHP implementations for compatibility:
- `nullpunkt/lz-string-php`
- `jezevec10/lz-string-php`  
- `netom/lz-string-php`

## Development Environment

- PHP 7.4.33
- MAMP/XAMPP local server environment
- cURL extension required for API calls
- OpenSSL extension required for encryption

## API Endpoints

Base URL: `https://apijkn-dev.bpjs-kesehatan.go.id`
- GET `/wskemdikbud/Services/pesertaasn/read/page/{page}/limit/{limit}/unorid/{unorid}`

## Credentials (Development)
- Consumer ID: 1171
- Consumer Secret: 9g7gcvw1fS  
- User Key: 95fbb45a93ef7a55a4ed1ef281de2b49