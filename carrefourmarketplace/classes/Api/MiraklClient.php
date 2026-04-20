<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 *
 * Thin HTTP client for the Mirakl Seller API (MMP).
 * - Authorization header is the raw API key (no Bearer prefix — Mirakl convention).
 * - Retries on 429 and 5xx with exponential backoff + jitter.
 * - Transport is swappable for unit tests via setTransport().
 */
if (!defined('_PS_VERSION_') && !defined('CARREFOUR_TEST_MODE')) {
    exit;
}

require_once __DIR__ . '/MiraklException.php';

class MiraklClient
{
    const DEFAULT_TIMEOUT = 30;
    const DEFAULT_CONNECT_TIMEOUT = 10;
    const DEFAULT_MAX_ATTEMPTS = 5;
    const MAX_BACKOFF_MS = 60000;

    /** @var string */
    private $endpoint;

    /** @var string */
    private $apiKey;

    /** @var string|null */
    private $shopId;

    /** @var callable|null */
    private $transport;

    /** @var object|null CarrefourLogger-like instance (info/warn/error methods) */
    private $logger;

    public function __construct($endpoint, $apiKey, $shopId = null, $logger = null)
    {
        $this->endpoint = rtrim((string) $endpoint, '/');
        $this->apiKey = (string) $apiKey;
        $this->shopId = $shopId !== null && $shopId !== '' ? (string) $shopId : null;
        $this->logger = $logger;
    }

    /**
     * Override the transport for tests. $transport(array $request) must return
     * ['status_code' => int, 'headers' => array, 'body' => string] or throw MiraklNetworkException.
     */
    public function setTransport(callable $transport)
    {
        $this->transport = $transport;
    }

    /**
     * A01 — retrieve current shop/account information.
     * Used by the "Test connection" button to verify credentials + endpoint reachability.
     *
     * @return array decoded response payload
     */
    public function testConnection()
    {
        /* Fast fail for interactive UI — max 2 attempts instead of the default 5 */
        $response = $this->get('/account', [], ['max_attempts' => 2]);

        return $response['decoded'];
    }

    public function get($path, array $query = [], array $options = [])
    {
        return $this->request('GET', $path, $query, null, $options);
    }

    public function post($path, $body = null, array $query = [], array $options = [])
    {
        return $this->request('POST', $path, $query, $body, $options);
    }

    public function put($path, $body = null, array $query = [], array $options = [])
    {
        return $this->request('PUT', $path, $query, $body, $options);
    }

    public function delete($path, array $query = [], array $options = [])
    {
        return $this->request('DELETE', $path, $query, null, $options);
    }

    /**
     * Low-level request. Returns an array with status_code, headers, body, decoded (if JSON).
     * Throws a typed MiraklException on HTTP errors.
     */
    public function request($method, $path, array $query = [], $body = null, array $options = [])
    {
        $url = $this->endpoint . '/' . ltrim($path, '/');
        if (!empty($query)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query);
        }

        $headers = [
            'Authorization: ' . $this->apiKey,
            'Accept: application/json',
        ];
        $rawBody = null;
        if ($body !== null) {
            if (is_array($body) || is_object($body)) {
                $rawBody = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $headers[] = 'Content-Type: application/json';
            } else {
                $rawBody = (string) $body;
            }
        }

        $request = [
            'method' => strtoupper($method),
            'url' => $url,
            'headers' => $headers,
            'body' => $rawBody,
            'timeout' => isset($options['timeout']) ? (int) $options['timeout'] : self::DEFAULT_TIMEOUT,
            'connect_timeout' => isset($options['connect_timeout']) ? (int) $options['connect_timeout'] : self::DEFAULT_CONNECT_TIMEOUT,
        ];

        $maxAttempts = isset($options['max_attempts']) ? (int) $options['max_attempts'] : self::DEFAULT_MAX_ATTEMPTS;
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; ++$attempt) {
            try {
                $response = call_user_func($this->transport ?: [$this, 'curlTransport'], $request);
            } catch (MiraklNetworkException $e) {
                $lastException = $e;
                $this->logWarn('mirakl.network', [
                    'attempt' => $attempt,
                    'url' => $request['url'],
                    'error' => $e->getMessage(),
                ]);
                if ($attempt < $maxAttempts) {
                    $this->sleepMs($this->backoffMs($attempt));

                    continue;
                }

                throw $e;
            }

            $status = (int) $response['status_code'];
            $responseBody = isset($response['body']) ? (string) $response['body'] : '';

            if ($status >= 200 && $status < 300) {
                $decoded = $this->tryDecodeJson($responseBody);
                $this->logInfo('mirakl.ok', [
                    'method' => $request['method'],
                    'path' => $path,
                    'status' => $status,
                    'attempt' => $attempt,
                ]);

                return [
                    'status_code' => $status,
                    'headers' => isset($response['headers']) ? $response['headers'] : [],
                    'body' => $responseBody,
                    'decoded' => $decoded,
                ];
            }

            $decoded = $this->tryDecodeJson($responseBody);
            $errorCode = $this->extractMiraklErrorCode($decoded);
            $errorMessage = $this->extractMiraklErrorMessage($decoded, $responseBody, $status);

            $this->logWarn('mirakl.http_error', [
                'method' => $request['method'],
                'path' => $path,
                'status' => $status,
                'attempt' => $attempt,
                'error_code' => $errorCode,
                'error' => mb_substr($errorMessage, 0, 300),
            ]);

            if ($status === 401 || $status === 403) {
                throw new MiraklAuthException($errorMessage, $status, $errorCode, $responseBody);
            }
            if ($status === 404) {
                throw new MiraklNotFoundException($errorMessage, $status, $errorCode, $responseBody);
            }
            if ($status === 429) {
                $lastException = new MiraklRateLimitException($errorMessage, $status, $errorCode, $responseBody);
                if ($attempt < $maxAttempts) {
                    $this->sleepMs($this->backoffMs($attempt));

                    continue;
                }

                throw $lastException;
            }
            if ($status >= 500) {
                $lastException = new MiraklServerException($errorMessage, $status, $errorCode, $responseBody);
                if ($attempt < $maxAttempts) {
                    $this->sleepMs($this->backoffMs($attempt));

                    continue;
                }

                throw $lastException;
            }

            throw new MiraklValidationException($errorMessage, $status, $errorCode, $responseBody);
        }

        /* Defensive fallthrough — should never reach here. */
        throw $lastException ?: new MiraklException('Unknown Mirakl client failure');
    }

    /* -------------------------------------------------------------
     * Transport: cURL (default)
     * ----------------------------------------------------------- */

    private function curlTransport(array $request)
    {
        if (!function_exists('curl_init')) {
            throw new MiraklNetworkException('cURL extension is required but not available');
        }

        $ch = curl_init();
        $opts = [
            CURLOPT_URL => $request['url'],
            CURLOPT_CUSTOMREQUEST => $request['method'],
            CURLOPT_HTTPHEADER => $request['headers'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => $request['timeout'],
            CURLOPT_CONNECTTIMEOUT => $request['connect_timeout'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADER => true,
        ];
        if ($request['body'] !== null) {
            $opts[CURLOPT_POSTFIELDS] = $request['body'];
        }
        curl_setopt_array($ch, $opts);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            $errno = curl_errno($ch);
            curl_close($ch);

            throw new MiraklNetworkException(sprintf('cURL error (%d): %s', $errno, $err));
        }

        $info = curl_getinfo($ch);
        $status = (int) $info['http_code'];
        $headerSize = (int) $info['header_size'];
        $rawHeaders = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);
        curl_close($ch);

        return [
            'status_code' => $status,
            'headers' => $this->parseHeaders($rawHeaders),
            'body' => (string) $body,
        ];
    }

    private function parseHeaders($raw)
    {
        $headers = [];
        if (!is_string($raw)) {
            return $headers;
        }
        foreach (preg_split("/\r\n|\n|\r/", trim($raw)) as $line) {
            if (strpos($line, ':') === false) {
                continue;
            }
            list($key, $value) = explode(':', $line, 2);
            $headers[strtolower(trim($key))] = trim($value);
        }

        return $headers;
    }

    /* -------------------------------------------------------------
     * Helpers
     * ----------------------------------------------------------- */

    private function tryDecodeJson($raw)
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    private function extractMiraklErrorCode($decoded)
    {
        if (!is_array($decoded)) {
            return null;
        }
        if (isset($decoded['error_code']) && is_string($decoded['error_code'])) {
            return $decoded['error_code'];
        }
        if (isset($decoded['errors'][0]['error_code']) && is_string($decoded['errors'][0]['error_code'])) {
            return $decoded['errors'][0]['error_code'];
        }

        return null;
    }

    private function extractMiraklErrorMessage($decoded, $rawBody, $status)
    {
        if (is_array($decoded)) {
            if (isset($decoded['message']) && is_string($decoded['message']) && $decoded['message'] !== '') {
                return $decoded['message'];
            }
            if (isset($decoded['errors'][0]['message']) && is_string($decoded['errors'][0]['message'])) {
                return $decoded['errors'][0]['message'];
            }
        }
        if (is_string($rawBody) && $rawBody !== '') {
            return mb_substr($rawBody, 0, 500);
        }

        return sprintf('HTTP %d', $status);
    }

    private function backoffMs($attempt)
    {
        $base = (int) min(self::MAX_BACKOFF_MS, 1000 * pow(2, max(0, $attempt - 1)));
        $jitter = function_exists('random_int') ? random_int(0, (int) ($base * 0.25)) : mt_rand(0, (int) ($base * 0.25));

        return $base + $jitter;
    }

    private function sleepMs($ms)
    {
        if (defined('CARREFOUR_TEST_MODE')) {
            return;
        }
        usleep(max(0, (int) $ms) * 1000);
    }

    private function logInfo($channel, array $context = [])
    {
        if ($this->logger && method_exists($this->logger, 'info')) {
            $this->logger->info($channel, $context);
        }
    }

    private function logWarn($channel, array $context = [])
    {
        if ($this->logger && method_exists($this->logger, 'warn')) {
            $this->logger->warn($channel, $context);
        }
    }
}
