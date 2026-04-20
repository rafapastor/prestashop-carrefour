<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 *
 * Mirakl API exception hierarchy. All subclasses live in this file so they are
 * loaded together — MiraklClient.php require_once's this file explicitly.
 */
if (!defined('_PS_VERSION_') && !defined('CARREFOUR_TEST_MODE')) {
    exit;
}

class MiraklException extends \RuntimeException
{
    /** @var int HTTP status (0 when unavailable, e.g. network error) */
    protected $statusCode;

    /** @var string|null Mirakl-specific error code like "OF-23" if present */
    protected $errorCode;

    /** @var string|null Raw response body */
    protected $responseBody;

    public function __construct($message, $statusCode = 0, $errorCode = null, $responseBody = null, ?\Throwable $previous = null)
    {
        parent::__construct((string) $message, 0, $previous);
        $this->statusCode = (int) $statusCode;
        $this->errorCode = $errorCode;
        $this->responseBody = $responseBody;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function getErrorCode()
    {
        return $this->errorCode;
    }

    public function getResponseBody()
    {
        return $this->responseBody;
    }
}

/** 401 / 403 — credentials invalid or insufficient. Not retryable. */
class MiraklAuthException extends MiraklException
{
}

/** 404 — resource not found. Not retryable. */
class MiraklNotFoundException extends MiraklException
{
}

/** 429 — rate limited. Retryable with backoff. */
class MiraklRateLimitException extends MiraklException
{
}

/** 5xx — server error. Retryable with backoff. */
class MiraklServerException extends MiraklException
{
}

/** 4xx other than 401/403/404/429 — bad request. Not retryable. */
class MiraklValidationException extends MiraklException
{
}

/** cURL or network failure before an HTTP response. Retryable. */
class MiraklNetworkException extends MiraklException
{
}
