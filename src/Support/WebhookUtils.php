<?php

namespace WizardingCode\WebhookOwlery\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Random\RandomException;

class WebhookUtils
{
    /**
     * Generate a secure webhook secret.
     *
     * @param int $length Length of the secret
     *
     * @throws RandomException
     *
     * @return string The generated secret
     */
    public static function generateSecret(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Normalize a URL to ensure it's valid and has a consistent format.
     *
     * @param string $url The URL to normalize
     *
     * @throws InvalidArgumentException If the URL is invalid
     *
     * @return string The normalized URL
     */
    public static function normalizeUrl(string $url): string
    {
        // Add scheme if missing
        if (! preg_match('~^(?:f|ht)tps?://~i', $url)) {
            $url = 'https://' . $url;
        }

        // Check if the URL is valid
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Invalid URL format');
        }

        // Normalize the URL
        $parsed = parse_url($url);

        // Build the URL without trailing slash
        $normalizedUrl = $parsed['scheme'] . '://' . $parsed['host'];

        if (isset($parsed['port'])) {
            $normalizedUrl .= ':' . $parsed['port'];
        }

        if (isset($parsed['path'])) {
            // Ensure path starts with a slash
            $path = $parsed['path'];
            if (! str_starts_with($path, '/')) {
                $path = '/' . $path;
            }
            $normalizedUrl .= rtrim($path, '/');
        }

        if (isset($parsed['query'])) {
            $normalizedUrl .= '?' . $parsed['query'];
        }

        if (isset($parsed['fragment'])) {
            $normalizedUrl .= '#' . $parsed['fragment'];
        }

        return $normalizedUrl;
    }

    /**
     * Calculate a webhook signature using HMAC.
     *
     * @param string $payload   The payload to sign
     * @param string $secret    The secret key
     * @param string $algorithm The hash algorithm to use
     *
     * @return string The calculated signature
     */
    public static function calculateSignature(string $payload, string $secret, string $algorithm = 'sha256'): string
    {
        return hash_hmac($algorithm, $payload, $secret);
    }

    /**
     * Extract the webhook event name from request.
     *
     * @param Request $request The HTTP request
     * @param string  $source  The source service
     * @param array   $options Options for extraction
     *
     * @return string|null The extracted event name or null if not found
     */
    public static function extractEventName(Request $request, string $source, array $options = []): ?string
    {
        // Check if event name is provided in options
        if (isset($options['event'])) {
            return $options['event'];
        }

        // Get event from standard header
        $eventHeader = $options['event_header'] ?? config('webhook-owlery.receiving.event_header', 'X-Webhook-Event');
        if ($request->hasHeader($eventHeader)) {
            return $request->header($eventHeader);
        }

        // Check for source-specific event extraction
        return match (strtolower($source)) {
            'stripe' => self::extractStripeEvent($request),
            'github' => self::extractGithubEvent($request),
            'shopify' => self::extractShopifyEvent($request),
            'paypal' => self::extractPaypalEvent($request),
            default => self::extractGenericEvent($request, $options),
        };
    }

    /**
     * Extract Stripe event from request.
     */
    private static function extractStripeEvent(Request $request): ?string
    {
        $payload = $request->input();

        return $payload['type'] ?? null;
    }

    /**
     * Extract GitHub event from request.
     */
    private static function extractGithubEvent(Request $request): ?string
    {
        if ($request->hasHeader('X-GitHub-Event')) {
            return 'github.' . $request->header('X-GitHub-Event');
        }

        return null;
    }

    /**
     * Extract Shopify event from request.
     */
    private static function extractShopifyEvent(Request $request): ?string
    {
        if ($request->hasHeader('X-Shopify-Topic')) {
            return $request->header('X-Shopify-Topic');
        }

        return null;
    }

    /**
     * Extract PayPal event from request.
     */
    private static function extractPaypalEvent(Request $request): ?string
    {
        $payload = $request->input();

        return $payload['event_type'] ?? null;
    }

    /**
     * Extract a generic event from request.
     */
    private static function extractGenericEvent(Request $request, array $options): ?string
    {
        $payload = $request->input();

        // Common keys that might contain the event type
        $eventKeys = $options['event_keys'] ?? [
            'event', 'event_type', 'type', 'topic', 'action', 'trigger',
        ];

        foreach ($eventKeys as $key) {
            if (Arr::has($payload, $key)) {
                return Arr::get($payload, $key);
            }
        }

        return null;
    }

    /**
     * Generate a webhook URL for a specific source.
     *
     * @param string $source  The source identifier
     * @param array  $options URL generation options
     *
     * @return string The generated URL
     */
    public static function generateWebhookUrl(string $source, array $options = []): string
    {
        $prefix = config('webhook-owlery.routes.prefix', 'webhooks');
        $path = "{$prefix}/{$source}";

        if (isset($options['absolute']) && $options['absolute'] === false) {
            return $path;
        }

        $secure = $options['secure'] ?? true;

        if (isset($options['domain'])) {
            return URL::to($path, [], $secure, $options['domain']);
        }

        return URL::to($path, [], $secure);
    }

    /**
     * Parse a webhook URL into components.
     *
     * @param string $url The webhook URL
     *
     * @return array The parsed components
     */
    public static function parseWebhookUrl(string $url): array
    {
        $parsed = parse_url($url);

        $parts = [
            'scheme' => $parsed['scheme'] ?? null,
            'host' => $parsed['host'] ?? null,
            'port' => $parsed['port'] ?? null,
            'path' => $parsed['path'] ?? null,
            'query' => $parsed['query'] ?? null,
        ];

        // Extract source from path if it matches our pattern
        if (isset($parsed['path'])) {
            $pathParts = explode('/', trim($parsed['path'], '/'));
            $prefix = config('webhook-owlery.routes.prefix', 'webhooks');

            if (count($pathParts) >= 2 && $pathParts[0] === $prefix) {
                $parts['source'] = $pathParts[1];
            }
        }

        return $parts;
    }

    /**
     * Sanitize a payload for logging (remove sensitive fields).
     *
     * @param array $payload       The original payload
     * @param array $sensitiveKeys Keys to sanitize
     *
     * @return array The sanitized payload
     */
    public static function sanitizePayload(array $payload, array $sensitiveKeys = []): array
    {
        // Default sensitive keys
        $defaultSensitiveKeys = [
            'password', 'token', 'secret', 'key', 'authorization', 'auth',
            'credit_card', 'card', 'cvv', 'cvc', 'ccv', 'ssn', 'tax_id',
            'account_number', 'social_security', 'routing_number',
        ];

        $keysToSanitize = array_merge($defaultSensitiveKeys, $sensitiveKeys);

        return self::recursiveSanitize($payload, $keysToSanitize);
    }

    /**
     * Recursively sanitize an array.
     */
    protected static function recursiveSanitize(array $data, array $sensitiveKeys): array
    {
        foreach ($data as $key => $value) {
            // Check if key contains any sensitive words
            $isSensitive = false;
            foreach ($sensitiveKeys as $sensitiveKey) {
                if (stripos($key, $sensitiveKey) !== false) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                if (is_string($value)) {
                    // Mask the value based on length
                    $data[$key] = self::maskString($value);
                } else {
                    // Not a string, replace with a generic message
                    $data[$key] = '[REDACTED]';
                }
            } elseif (is_array($value)) {
                // Recursively process nested arrays
                $data[$key] = self::recursiveSanitize($value, $sensitiveKeys);
            }
        }

        return $data;
    }

    /**
     * Mask a string based on its length.
     */
    protected static function maskString(string $value): string
    {
        $length = strlen($value);

        if ($length === 0) {
            return '';
        }

        if ($length <= 4) {
            return '****';
        }

        if ($length <= 8) {
            return substr($value, 0, 1) . str_repeat('*', $length - 2) . substr($value, -1);
        }

        // Keep first and last two characters for longer strings
        return substr($value, 0, 2) . str_repeat('*', $length - 4) . substr($value, -2);
    }

    /**
     * Generate a unique ID for a webhook event.
     *
     * @param string $source  The source service
     * @param string $event   The event type
     * @param array  $payload The event payload
     *
     * @throws RandomException
     *
     * @return string The generated ID
     */
    public static function generateEventId(string $source, string $event, array $payload = []): string
    {
        // Try to extract a unique identifier from the payload
        $idFromPayload = self::extractIdFromPayload($payload, $source);

        if ($idFromPayload) {
            return "{$source}_{$event}_{$idFromPayload}";
        }

        // Fall back to a time-based ID with some randomness
        $timestamp = time();
        $random = bin2hex(random_bytes(4));

        return "{$source}_{$event}_{$timestamp}_{$random}";
    }

    /**
     * Try to extract a unique identifier from a payload.
     */
    private static function extractIdFromPayload(array $payload, string $source): ?string
    {
        // Common ID fields
        $idFields = ['id', 'uuid', 'guid', 'key', 'reference'];

        // Source-specific ID fields
        $sourceIdFields = match (strtolower($source)) {
            'stripe', 'shopify' => ['id'],
            'github' => ['id', 'delivery'],
            'paypal' => ['id', 'transaction_id'],
            default => [],
        };

        // Try source-specific fields first
        foreach ($sourceIdFields as $field) {
            if (Arr::has($payload, $field)) {
                return (string) Arr::get($payload, $field);
            }

            // Try with data/object prefix (common pattern)
            if (Arr::has($payload, "data.{$field}")) {
                return (string) Arr::get($payload, "data.{$field}");
            }

            if (Arr::has($payload, "object.{$field}")) {
                return (string) Arr::get($payload, "object.{$field}");
            }
        }

        // Try common fields
        foreach ($idFields as $field) {
            if (Arr::has($payload, $field)) {
                return (string) Arr::get($payload, $field);
            }
        }

        return null;
    }

    /**
     * Create a deep clone of an array.
     */
    public static function deepCloneArray(array $array): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result[$key] = self::deepCloneArray($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Get a friendly name for a webhook source.
     */
    public static function getFriendlySourceName(string $source): string
    {
        $knownSources = [
            'stripe' => 'Stripe',
            'github' => 'GitHub',
            'shopify' => 'Shopify',
            'paypal' => 'PayPal',
            'slack' => 'Slack',
            'discord' => 'Discord',
            'twilio' => 'Twilio',
            'sendgrid' => 'SendGrid',
            'mailchimp' => 'Mailchimp',
            'hubspot' => 'HubSpot',
            'salesforce' => 'Salesforce',
            'intercom' => 'Intercom',
            'zendesk' => 'Zendesk',
        ];

        return $knownSources[$source] ?? Str::title($source);
    }

    /**
     * Get a rate limiting key for a webhook.
     */
    public static function getRateLimitKey(string $source, ?Request $request = null): string
    {
        if (! $request) {
            return "webhook-owlery:{$source}";
        }

        $ip = $request->ip();

        return "webhook-owlery:{$source}:{$ip}";
    }

    /**
     * Generate debug information about a webhook event.
     */
    public static function generateDebugInfo(string $source, string $event, Request $request, array $options = []): array
    {
        $info = [
            'source' => $source,
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
            'request' => [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
        ];

        // Include headers if requested
        if ($options['include_headers'] ?? true) {
            $headers = array_map(static function ($values) {
                return count($values) === 1 ? $values[0] : $values;
            }, (array) $request->headers);

            $info['request']['headers'] = self::sanitizeHeaders($headers);
        }

        // Include payload if requested
        if ($options['include_payload'] ?? true) {
            try {
                $payload = $request->input();
                $info['request']['payload'] = self::sanitizePayload($payload);
            } catch (\Throwable $e) {
                $info['request']['payload_error'] = $e->getMessage();
            }
        }

        return $info;
    }

    /**
     * Sanitize HTTP headers to remove sensitive information.
     */
    public static function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = [
            'authorization', 'cookie', 'x-api-key', 'x-auth-token',
            'x-stripe-signature', 'x-shopify-hmac-sha256', 'x-hub-signature',
        ];

        foreach ($headers as $key => $value) {
            if (in_array(strtolower($key), $sensitiveHeaders)) {
                $headers[$key] = '[REDACTED]';
            }
        }

        return $headers;
    }
}
