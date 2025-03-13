<?php

namespace WizardingCode\WebhookOwlery\Support;

use JsonException;
use Psr\Http\Message\ResponseInterface;

class WebhookResponseAnalyzer
{
    /**
     * Analyze a webhook response to determine if it was successful.
     *
     * @param ResponseInterface $response The HTTP response
     * @param array             $options  Analysis options
     *
     * @throws JsonException
     *
     * @return bool Whether the response indicates success
     */
    final public function isSuccessful(ResponseInterface $response, array $options = []): bool
    {
        // By default, consider 2xx status codes as successful
        $successStatuses = $options['success_status_codes'] ?? range(200, 299);

        // Check status code
        $statusCode = $response->getStatusCode();
        if (! in_array($statusCode, $successStatuses, true)) {
            return false;
        }

        // If custom success validation is provided, use it
        if (isset($options['success_validator']) && is_callable($options['success_validator'])) {
            return $options['success_validator']($response);
        }

        // For certain services, check response body for success indicators
        if (isset($options['source'])) {
            return $this->checkServiceSpecificSuccess($response, $options['source']);
        }

        return true;
    }

    /**
     * Check service-specific success indicators in response body.
     *
     * @throws JsonException
     */
    private function checkServiceSpecificSuccess(ResponseInterface $response, string $source): bool
    {
        $body = (string) $response->getBody();

        // If body is empty, rely on status code only
        if (empty($body)) {
            return true;
        }

        // Try to parse as JSON
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Not valid JSON, rely on status code
            return true;
        }

        // Check known service-specific error indicators
        return match (strtolower($source)) {
            'stripe' => ! isset($data['error']),
            'shopify' => ! isset($data['errors']),
            'github' => ! isset($data['message'], $data['documentation_url']),
            'paypal' => ! isset($data['error']) && ! isset($data['error_description']),
            'slack' => $data['ok'] ?? true,
            default => true,
        };
    }

    /**
     * Extract error information from a response.
     *
     * @throws JsonException
     *
     * @return array Error information
     */
    final public function extractErrorInfo(ResponseInterface $response, array $options = []): array
    {
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        $error = [
            'status_code' => $statusCode,
            'message' => $this->getStatusMessage($statusCode),
            'body' => $body,
        ];

        // Try to parse JSON response
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        if (json_last_error() === JSON_ERROR_NONE) {
            // Extract error information based on service
            if (isset($options['source'])) {
                $sourceError = $this->extractServiceSpecificError($data, $options['source']);
                if ($sourceError) {
                    $error = array_merge($error, $sourceError);
                }
            } else {
                // Generic error extraction for JSON responses
                $error = $this->extractGenericJsonError($data, $error);
            }
        }

        return $error;
    }

    /**
     * Extract service-specific error from response data.
     */
    private function extractServiceSpecificError(array $data, string $source): ?array
    {
        switch (strtolower($source)) {
            case 'stripe':
                if (isset($data['error'])) {
                    return [
                        'type' => $data['error']['type'] ?? 'unknown',
                        'code' => $data['error']['code'] ?? null,
                        'message' => $data['error']['message'] ?? 'Unknown error',
                        'param' => $data['error']['param'] ?? null,
                    ];
                }
                break;

            case 'shopify':
                if (isset($data['errors'])) {
                    return [
                        'message' => is_array($data['errors'])
                            ? implode(', ', $this->flattenErrors($data['errors']))
                            : (string) $data['errors'],
                    ];
                }
                break;

            case 'github':
                if (isset($data['message'])) {
                    return [
                        'message' => $data['message'],
                        'documentation_url' => $data['documentation_url'] ?? null,
                    ];
                }
                break;

            case 'paypal':
                if (isset($data['error'])) {
                    return [
                        'type' => $data['error'],
                        'message' => $data['error_description'] ?? 'Unknown error',
                    ];
                }
                break;

            case 'slack':
                if (isset($data['ok']) && $data['ok'] === false) {
                    return [
                        'code' => $data['error'] ?? 'unknown_error',
                        'message' => $this->getSlackErrorMessage($data['error'] ?? 'unknown_error'),
                    ];
                }
                break;
        }

        return null;
    }

    /**
     * Extract error information from generic JSON response.
     */
    private function extractGenericJsonError(array $data, array $defaultError): array
    {
        // Common error fields in APIs
        $possibleErrorKeys = [
            'error', 'errors', 'message', 'error_message',
            'error_description', 'errorMessage', 'fault',
            'reason', 'details', 'status',
        ];

        foreach ($possibleErrorKeys as $key) {
            if (isset($data[$key])) {
                if (is_string($data[$key])) {
                    $defaultError['message'] = $data[$key];
                } elseif (is_array($data[$key])) {
                    // Check for message inside the error object
                    if (isset($data[$key]['message'])) {
                        $defaultError['message'] = $data[$key]['message'];
                    } elseif (isset($data[$key]['description'])) {
                        $defaultError['message'] = $data[$key]['description'];
                    } else {
                        // Try to flatten the error array
                        $defaultError['message'] = implode(', ', $this->flattenErrors($data[$key]));
                    }

                    // Check for error code
                    if (isset($data[$key]['code'])) {
                        $defaultError['code'] = $data[$key]['code'];
                    } elseif (isset($data[$key]['type'])) {
                        $defaultError['type'] = $data[$key]['type'];
                    }
                }
                break;
            }
        }

        return $defaultError;
    }

    /**
     * Flatten a nested error array into a simple array of messages.
     */
    private function flattenErrors(array $errors): array
    {
        $result = [];

        foreach ($errors as $key => $value) {
            if (is_string($value)) {
                $result[] = $value;
            } elseif (is_array($value)) {
                if (isset($value['message'])) {
                    $result[] = $value['message'];
                } elseif (isset($value['description'])) {
                    $result[] = $value['description'];
                } else {
                    $result = array_merge($result, $this->flattenErrors($value));
                }
            } elseif (is_scalar($value)) {
                $result[] = $key . ': ' . (string) $value;
            }
        }

        return $result;
    }

    /**
     * Get a human-readable message for an HTTP status code.
     */
    private function getStatusMessage(int $code): string
    {
        $messages = [
            // 1xx - Informational
            100 => 'Continue',
            101 => 'Switching Protocols',
            102 => 'Processing',

            // 2xx - Success
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            207 => 'Multi-Status',
            208 => 'Already Reported',
            226 => 'IM Used',

            // 3xx - Redirection
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            307 => 'Temporary Redirect',
            308 => 'Permanent Redirect',

            // 4xx - Client Error
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Payload Too Large',
            414 => 'URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Range Not Satisfiable',
            417 => 'Expectation Failed',
            418 => 'I\'m a teapot',
            421 => 'Misdirected Request',
            422 => 'Unprocessable Entity',
            423 => 'Locked',
            424 => 'Failed Dependency',
            425 => 'Too Early',
            426 => 'Upgrade Required',
            428 => 'Precondition Required',
            429 => 'Too Many Requests',
            431 => 'Request Header Fields Too Large',
            451 => 'Unavailable For Legal Reasons',

            // 5xx - Server Error
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported',
            506 => 'Variant Also Negotiates',
            507 => 'Insufficient Storage',
            508 => 'Loop Detected',
            510 => 'Not Extended',
            511 => 'Network Authentication Required',
        ];

        return $messages[$code] ?? 'Unknown Status Code';
    }

    /**
     * Get a human-readable message for a Slack error code.
     */
    private function getSlackErrorMessage(string $code): string
    {
        $messages = [
            'channel_not_found' => 'The specified channel was not found',
            'not_in_channel' => 'The user is not in the specified channel',
            'is_archived' => 'The channel has been archived',
            'msg_too_long' => 'The message is too long',
            'no_text' => 'No message text was provided',
            'rate_limited' => 'The application has been rate limited',
            'invalid_auth' => 'Invalid authentication token',
            'not_authed' => 'No authentication token provided',
            'invalid_args' => 'Invalid arguments were provided',
            'invalid_arg_name' => 'Invalid argument name',
            'invalid_charset' => 'Invalid character set in arguments',
            'invalid_form_data' => 'Invalid form data was submitted',
            'invalid_post_type' => 'Invalid post type was specified',
            'missing_post_type' => 'Post type was not specified',
            'team_added_to_org' => 'The team has been added to an organization',
            'request_timeout' => 'The request timed out',
            'fatal_error' => 'A fatal error occurred',
        ];

        return $messages[$code] ?? 'Unknown error: ' . $code;
    }

    /**
     * Determine if a response should trigger a retry.
     *
     * @throws JsonException
     */
    final public function shouldRetry(ResponseInterface $response, array $options = []): bool
    {
        $statusCode = $response->getStatusCode();

        // Status codes that typically indicate temporary failures
        $retryStatuses = $options['retry_status_codes'] ?? [408, 425, 429, 500, 502, 503, 504];

        // Check if status code indicates retry
        if (in_array($statusCode, $retryStatuses, true)) {
            return true;
        }

        // Check for rate limiting headers
        if ($this->hasRateLimitingHeaders($response)) {
            return true;
        }

        // Check for specific error types in response body
        $body = (string) $response->getBody();
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        if (json_last_error() === JSON_ERROR_NONE) {
            // Check for rate limiting or temporary failure messages
            return $this->hasRetryableErrors($data, $options['source'] ?? null);
        }

        return false;
    }

    /**
     * Check if response has rate limiting headers.
     */
    private function hasRateLimitingHeaders(ResponseInterface $response): bool
    {
        // Common rate limiting headers
        $rateLimitHeaders = [
            'Retry-After',
            'X-RateLimit-Reset',
            'X-Rate-Limit-Reset',
        ];

        foreach ($rateLimitHeaders as $header) {
            if ($response->hasHeader($header)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if response body contains retryable errors.
     *
     * @throws JsonException
     */
    private function hasRetryableErrors(?array $data, ?string $source): bool
    {
        if (! $data) {
            return false;
        }

        // Generic error types that suggest retrying
        $retryableKeywords = [
            'rate limit', 'ratelimit', 'too many requests', 'timeout',
            'temporarily unavailable', 'maintenance', 'overloaded',
            'try again', 'temporary', 'capacity', 'busy',
        ];

        // Check for source-specific retry indicators
        if ($source) {
            switch (strtolower($source)) {
                case 'stripe':
                    return isset($data['error']['type']) &&
                        in_array($data['error']['type'], ['rate_limit_error', 'idempotency_error']);

                case 'shopify':
                    return isset($data['errors']) && is_string($data['errors']) &&
                        str_contains(strtolower($data['errors']), 'rate limit');

                case 'github':
                    return isset($data['message']) &&
                        (str_contains(strtolower($data['message']), 'rate limit') ||
                            str_contains(strtolower($data['message']), 'abuse detection'));

                case 'slack':
                    return isset($data['error']) &&
                        in_array($data['error'], ['rate_limited', 'service_unavailable', 'fatal_error']);
            }
        }

        // Check for generic retryable error messages
        $responseJson = json_encode($data, JSON_THROW_ON_ERROR);
        foreach ($retryableKeywords as $keyword) {
            if (stripos($responseJson, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate the retry delay based on the response.
     *
     * @param int $attempt The current attempt number (1-based)
     *
     * @return int Delay in seconds
     */
    final public function calculateRetryDelay(ResponseInterface $response, int $attempt, array $options = []): int
    {
        // Check for Retry-After header
        if ($response->hasHeader('Retry-After')) {
            $retryAfter = $response->getHeaderLine('Retry-After');
            if (is_numeric($retryAfter)) {
                return (int) $retryAfter;
            }

            // Parse HTTP date
            $date = \DateTime::createFromFormat(\DateTime::RFC7231, $retryAfter);
            if ($date) {
                return max(1, $date->getTimestamp() - time());
            }
        }

        // Check for rate limit reset headers
        $rateLimitHeaders = [
            'X-RateLimit-Reset',
            'X-Rate-Limit-Reset',
        ];

        foreach ($rateLimitHeaders as $header) {
            if ($response->hasHeader($header)) {
                $resetTime = (int) $response->getHeaderLine($header);
                if ($resetTime > 0) {
                    // Some APIs return a timestamp, others return seconds
                    if ($resetTime > time() + 86400) {
                        // It's a timestamp
                        return max(1, $resetTime - time());
                    }

                    // It's a delay in seconds
                    return $resetTime;
                }
            }
        }

        // Default to exponential backoff
        $baseDelay = $options['base_delay'] ?? 30;
        $maxDelay = $options['max_delay'] ?? 3600; // 1 hour
        $backoffMultiplier = $options['backoff_multiplier'] ?? 2;

        // Calculate delay with exponential backoff
        $delay = $baseDelay * pow($backoffMultiplier, $attempt - 1);

        // Add some jitter to prevent all retries happening simultaneously
        $jitter = $delay * 0.2 * (mt_rand() / mt_getrandmax());
        $delay += $jitter;

        // Cap at max delay
        return min((int) $delay, $maxDelay);
    }
}
