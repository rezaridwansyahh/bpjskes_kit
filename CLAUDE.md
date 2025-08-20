# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a BPJSTK (BPJS Tenaga Kerja) API client implementation for integrating with BPJS Kesehatan's ASN (Aparatur Sipil Negara) participant data services. The project handles encrypted API responses that require AES-256 decryption followed by LZ-String decompression.

**Available implementations:**
- **Node.js** (Recommended): `bpjstk-client.js` - Full-featured with excellent error handling
- **PHP**: `BPJSTK_complete.php` - Basic implementation with LZ-String issues

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

### Node.js (Recommended)
```bash
# Install dependencies
npm install

# Run examples
node example.js

# Run tests
npm test
```

### PHP (Legacy)
```bash
# Install dependencies
composer install

# Test API integration
php BPJSTK_complete.php

# Run usage example
php example_usage.php
```

## File Structure

### Node.js Implementation (Recommended)
- `bpjstk-client.js` - Main Node.js API client implementation
- `example.js` - Usage examples and performance tests
- `package.json` - Node.js dependencies and scripts
- `README.md` - Comprehensive documentation
- `decrypted_data.txt` - Sample decrypted data for analysis

### PHP Implementation (Legacy)  
- `BPJSTK_complete.php` - PHP API client with error handling
- `example_usage.php` - PHP usage examples
- `bpjs_tk_kit/decrypt.php` - Basic decryption utility functions
- `composer.json` - PHP dependencies (3 LZ-String implementations)
- `vendor/` - Composer dependencies

### Documentation
- `docs.txt` - API specification and sample responses
- `CLAUDE.md` - Development guidance for Claude Code

## Key Classes and Methods

### BPJSTKClient (Node.js - Recommended)
- `getASNData(page, limit, unorid)` - Clean JSON response for ASN data
- `getPesertaASN(page, limit, unorid, debug)` - Detailed response with debug info
- `makeRequest(endpoint, method, data, debug)` - Core API request handler
- `generateSignature(timestamp)` - HMAC-SHA256 signature generation
- `generateEncryptionKey(timestamp)` - AES encryption key creation
- `decryptResponse(encryptedData, key)` - AES-256 decryption (ECB/CBC)
- `decompressLZString(input)` - LZ-String decompression with fallbacks

### BPJSTKClient (PHP - Legacy)
- `makeRequest($endpoint, $method, $data, $debug)` - Main API request handler
- `getPesertaASN($page, $limit, $unorid, $debug)` - Get ASN participant data
- `getASNData($page, $limit, $unorid)` - Clean JSON response helper
- `generateSignature($timestamp)` - Generate HMAC-SHA256 signature
- `generateEncryptionKey($timestamp)` - Create AES encryption key
- `decryptResponse($encryptedData, $key)` - AES decryption with fallback modes

## Dependencies

### Node.js (Recommended)
- `lz-string`: Native JavaScript LZ-String library
- `axios`: HTTP client for API requests  
- `crypto`: Built-in Node.js cryptography

### PHP (Legacy)
- `nullpunkt/lz-string-php`: Primary LZ-String implementation
- `jezevec10/lz-string-php`: Alternative implementation
- `netom/lz-string-php`: Backup implementation

## Development Environment

### Node.js
- Node.js 14.0.0 or higher
- npm for package management
- Built-in crypto module for encryption

### PHP (Legacy)
- PHP 7.4.33 or higher
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