/**
 * BPJSTK API Client - Node.js Implementation
 * 
 * A complete implementation for interacting with BPJS TK API
 * including AES decryption and LZ-String decompression
 */

const crypto = require('crypto');
const axios = require('axios');
const LZString = require('lz-string');

class BPJSTKClient {
    constructor(consumerID, consumerSecret, userKey, baseURL = 'https://apijkn-dev.bpjs-kesehatan.go.id') {
        this.consumerID = consumerID;
        this.consumerSecret = consumerSecret;
        this.userKey = userKey;
        this.baseURL = baseURL;
    }

    /**
     * Generate HMAC-SHA256 signature as per BPJS specification
     */
    generateSignature(timestamp) {
        const message = `${this.consumerID}&${timestamp}`;
        const signature = crypto.createHmac('sha256', this.consumerSecret)
                               .update(message)
                               .digest('base64');
        return signature;
    }

    /**
     * Generate encryption key: SHA256(consumerID + consumerSecret + timestamp)
     */
    generateEncryptionKey(timestamp) {
        const keyString = this.consumerID + this.consumerSecret + timestamp;
        return crypto.createHash('sha256').update(keyString).digest();
    }

    /**
     * Decrypt AES-256 encrypted response
     */
    decryptResponse(encryptedData, key) {
        try {
            const data = Buffer.from(encryptedData, 'base64');
            
            // Try AES-256-ECB first (no IV needed)
            try {
                const decipher = crypto.createDecipher('aes-256-ecb', key);
                let decrypted = decipher.update(data, null, 'utf8');
                decrypted += decipher.final('utf8');
                return decrypted;
            } catch (ecbError) {
                // Try AES-256-CBC with IV
                if (data.length > 16) {
                    const iv = data.slice(0, 16);
                    const encrypted = data.slice(16);
                    const decipher = crypto.createDecipheriv('aes-256-cbc', key, iv);
                    let decrypted = decipher.update(encrypted, null, 'utf8');
                    decrypted += decipher.final('utf8');
                    return decrypted;
                }
                throw new Error('AES decryption failed: ' + ecbError.message);
            }
        } catch (error) {
            throw new Error(`AES decryption failed: ${error.message}`);
        }
    }

    /**
     * Decompress LZ-String data using native JavaScript library
     */
    decompressLZString(input) {
        if (!input || input.length === 0) return '';
        
        try {
            // Try different LZ-String decompression methods
            const methods = [
                'decompressFromEncodedURIComponent',
                'decompressFromUTF16',
                'decompressFromBase64',
                'decompress'
            ];
            
            for (const method of methods) {
                if (LZString[method]) {
                    const result = LZString[method](input);
                    if (result && result.length > 0) {
                        // Try to parse as JSON to verify it's valid
                        try {
                            JSON.parse(result);
                            return result; // Success!
                        } catch (jsonError) {
                            // Not JSON, but might still be valid data
                            if (result.length > 50) {
                                return result;
                            }
                        }
                    }
                }
            }
            
            // If all methods fail, return the input as-is
            return input;
            
        } catch (error) {
            // If decompression fails, return the input as-is
            console.warn('LZ-String decompression failed:', error.message);
            return input;
        }
    }

    /**
     * Make API request to BPJSTK endpoint and return clean JSON response
     */
    async makeRequest(endpoint, method = 'GET', data = null, debug = false) {
        try {
            const timestamp = Math.floor(Date.now() / 1000);
            const signature = this.generateSignature(timestamp);
            
            const headers = {
                'X-cons-id': this.consumerID,
                'X-timestamp': timestamp.toString(),
                'X-signature': signature,
                'user_key': this.userKey
            };
            
            if (method === 'POST' && data) {
                headers['Content-Type'] = 'application/json';
            }
            
            const config = {
                method: method,
                url: this.baseURL + endpoint,
                headers: headers,
                timeout: 30000
            };
            
            if (method === 'POST' && data) {
                config.data = data;
            }
            
            if (debug) {
                console.log('Making request to:', config.url);
                console.log('Headers:', headers);
            }
            
            const response = await axios(config);
            
            if (debug) {
                console.log('HTTP Status:', response.status);
                console.log('Response data keys:', Object.keys(response.data));
            }
            
            // Process encrypted response
            if (response.data && response.data.response && typeof response.data.response === 'string') {
                try {
                    if (debug) {
                        console.log('Processing encrypted response...');
                        console.log('Timestamp:', timestamp);
                        console.log('Encrypted data length:', response.data.response.length);
                    }
                    
                    // Step 1: Decrypt AES
                    const encryptionKey = this.generateEncryptionKey(timestamp);
                    const decrypted = this.decryptResponse(response.data.response, encryptionKey);
                    
                    if (debug) {
                        console.log('✓ AES decryption successful (', decrypted.length, 'characters)');
                        console.log('Decrypted (first 100 chars):', decrypted.substring(0, 100));
                    }
                    
                    // Step 2: LZ-String decompression
                    const decompressed = this.decompressLZString(decrypted);
                    
                    if (debug) {
                        console.log('✓ LZ-String decompression:', decompressed.length, 'characters');
                        if (decompressed !== decrypted) {
                            console.log('✓ Data was successfully decompressed');
                            console.log('Decompressed (first 200 chars):', decompressed.substring(0, 200));
                        } else {
                            console.log('⚠ Data unchanged - may not be LZ-String compressed');
                        }
                    }
                    
                    // Step 3: Parse JSON
                    try {
                        const finalData = JSON.parse(decompressed);
                        if (debug) {
                            console.log('✓ JSON parsing successful');
                        }
                        
                        return {
                            success: true,
                            code: response.data.metaData?.code || 200,
                            message: response.data.metaData?.message || 'OK',
                            data: finalData
                        };
                    } catch (jsonError) {
                        // Try parsing the original decrypted data
                        try {
                            const finalData = JSON.parse(decrypted);
                            if (debug) {
                                console.log('✓ JSON parsing successful with original decrypted data');
                            }
                            
                            return {
                                success: true,
                                code: response.data.metaData?.code || 200,
                                message: response.data.metaData?.message || 'OK',
                                data: finalData
                            };
                        } catch (originalJsonError) {
                            if (debug) {
                                console.log('⚠ JSON parsing failed:', jsonError.message);
                            }
                            
                            return {
                                success: false,
                                error: 'Failed to parse decrypted data as JSON: ' + jsonError.message,
                                code: response.data.metaData?.code || 500,
                                decrypted_data: decrypted,
                                decompressed_data: decompressed
                            };
                        }
                    }
                    
                } catch (processingError) {
                    if (debug) {
                        console.log('✗ Processing failed:', processingError.message);
                    }
                    
                    return {
                        success: false,
                        error: 'Decryption/Decompression failed: ' + processingError.message,
                        code: response.data.metaData?.code || 500
                    };
                }
            }
            
            // If response is not encrypted, return as-is
            return {
                success: true,
                code: response.data.metaData?.code || 200,
                message: response.data.metaData?.message || 'OK',
                data: response.data.response || response.data
            };
            
        } catch (error) {
            if (error.response) {
                return {
                    success: false,
                    error: `HTTP Error: ${error.response.status}`,
                    code: error.response.status
                };
            } else if (error.request) {
                return {
                    success: false,
                    error: 'Network Error: No response received',
                    code: 0
                };
            } else {
                return {
                    success: false,
                    error: 'Request Error: ' + error.message,
                    code: 0
                };
            }
        }
    }

    /**
     * Get ASN participant data with pagination
     */
    async getPesertaASN(page = 1, limit = 10, unorid = '', debug = false) {
        const endpoint = `/wskemdikbud/Services/pesertaasn/read/page/${page}/limit/${limit}/unorid/${unorid}`;
        return await this.makeRequest(endpoint, 'GET', null, debug);
    }

    /**
     * Get clean JSON response for ASN participant data
     */
    async getASNData(page = 1, limit = 10, unorid = '') {
        const result = await this.getPesertaASN(page, limit, unorid, false);
        
        if (result.success) {
            return {
                success: true,
                data: result.data,
                code: result.code,
                message: result.message
            };
        } else {
            return {
                success: false,
                error: result.error,
                code: result.code
            };
        }
    }
}

module.exports = BPJSTKClient;