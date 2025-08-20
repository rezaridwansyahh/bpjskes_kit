# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a BPJSTK (BPJS Tenaga Kerja) API client implementation for integrating with BPJS Kesehatan's ASN (Aparatur Sipil Negara) participant data services. The project handles encrypted API responses that require AES-256 decryption followed by LZ-String decompression.

**Current implementation:**
- **PHP**: `bpjstk_clean.php` - Production-ready implementation using partner's proven bpjs_tk_kit

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

### PHP Implementation
```bash
# Install dependencies (already included in bpjs_tk_kit/)
composer install

# Run the clean production client
php bpjstk_clean.php

# Test API integration
php -S localhost:8000 bpjstk_clean.php
```

## File Structure

### Core Implementation
- `bpjstk_clean.php` - Production-ready PHP API client with clean output
- `bpjs_tk_kit/` - Partner's working implementation toolkit
  - `decrypt.php` - Working AES decryption functions
  - `vendor/nullpunkt/lz-string-php/` - Working LZ-String library
- `composer.json` - PHP dependencies
- `vendor/` - Composer dependencies

### Documentation & Reference
- `docs.txt` - API specification and sample responses
- `CLAUDE.md` - Development guidance for Claude Code
- `decrypted_data.txt` - Sample decrypted data for analysis

## Key Classes and Methods

### BPJSTKClient (PHP)
- `makeAPICall($endpoint)` - Core API request handler with full processing
- `getPesertaASN($page, $limit, $unorid)` - Get ASN participant data with metadata
- `getASNRecords($page, $limit, $unorid)` - Get clean array of ASN records
- `generateSignature($timestamp)` - HMAC-SHA256 signature generation
- `generateEncryptionKey($timestamp)` - AES encryption key creation (partner's method)
- `stringDecrypt($string, $secret_key)` - AES-256-CBC decryption (partner's implementation)
- `decompress($string)` - LZ-String decompression using working nullpunkt library

## Dependencies

### PHP Implementation
- `nullpunkt/lz-string-php`: Working LZ-String implementation (from partner's bpjs_tk_kit)
- `jezevec10/lz-string-php`: Additional LZ-String implementation
- `netom/lz-string-php`: Backup LZ-String implementation
- cURL: For HTTP API requests
- OpenSSL: For AES-256 encryption/decryption

## Development Environment

### PHP Requirements
- PHP 7.4.33 or higher
- MAMP/XAMPP local server environment
- cURL extension required for API calls
- OpenSSL extension required for encryption
- Composer for dependency management

## API Endpoints

Base URL: `https://apijkn-dev.bpjs-kesehatan.go.id`
- GET `/wskemdikbud/Services/pesertaasn/read/page/{page}/limit/{limit}/unorid/{unorid}`

## Credentials (Development)
- Consumer ID: 1171
- Consumer Secret: 9g7gcvw1fS  
- User Key: 95fbb45a93ef7a55a4ed1ef281de2b49