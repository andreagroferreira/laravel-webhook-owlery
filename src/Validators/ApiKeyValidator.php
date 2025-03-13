<?php

namespace WizardingCode\WebhookOwlery\Validators;

use Illuminate\Http\Request;
use WizardingCode\WebhookOwlery\Contracts\SignatureValidatorContract;

class ApiKeyValidator implements SignatureValidatorContract
{
    /**
     * Validate a webhook using an API key.
     *
     * @param Request $request The HTTP request
     * @param string  $secret  The expected API key
     * @param array   $options Validation options
     *
     * @return bool Whether the API key is valid
     */
    final public function validate(Request $request, string $secret, array $options = []): bool
    {
        // Get the API key from the request
        $apiKey = $this->getSignatureFromRequest($request, $options);

        if (! $apiKey) {
            return false;
        }

        // Compare with expected API key using constant-time comparison to prevent timing attacks
        return hash_equals($secret, $apiKey);
    }

    /**
     * Generate an API key header.
     *
     * @param array|string $payload Not used for API key
     * @param string       $secret  The API key
     * @param array        $options Generation options
     *
     * @return string The API key
     */
    final public function generate(array|string $payload, string $secret, array $options = []): string
    {
        return $secret;
    }

    /**
     * Get the API key from a request.
     *
     * @param Request $request The HTTP request
     * @param array   $options Options including header name
     *
     * @return string|null The API key or null if not found
     */
    final public function getSignatureFromRequest(Request $request, array $options = []): ?string
    {
        // Check in header
        $headerName = $this->getSignatureHeaderName($options);
        if ($request->hasHeader($headerName)) {
            return $request->header($headerName);
        }

        // Check in query string
        $queryParam = $options['query_param'] ?? 'api_key';
        if ($request->has($queryParam)) {
            return $request->input($queryParam);
        }

        // Check in request body
        $bodyParam = $options['body_param'] ?? 'api_key';
        if ($request->has($bodyParam)) {
            return $request->input($bodyParam);
        }

        return null;
    }

    /**
     * Get the name of the API key header.
     *
     * @param array $options Options that may include a custom header name
     *
     * @return string The header name
     */
    final public function getSignatureHeaderName(array $options = []): string
    {
        return $options['api_key_header'] ?? 'X-API-Key';
    }

    /**
     * Get the algorithm used for signatures.
     *
     * Not applicable for API key validation, but required by interface.
     *
     * @param array $options Options that may include a custom algorithm
     *
     * @return string Always returns "api_key"
     */
    final public function getAlgorithm(array $options = []): string
    {
        return 'api_key';
    }
}
