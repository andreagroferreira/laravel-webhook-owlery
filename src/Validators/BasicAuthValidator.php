<?php

namespace WizardingCode\WebhookOwlery\Validators;

use Illuminate\Http\Request;
use WizardingCode\WebhookOwlery\Contracts\SignatureValidatorContract;

class BasicAuthValidator implements SignatureValidatorContract
{
    /**
     * Validate a webhook using HTTP Basic Authentication.
     *
     * @param Request $request The HTTP request
     * @param string  $secret  The secret key (expected to be in format "username:password")
     * @param array   $options Validation options
     *
     * @return bool Whether the authentication is valid
     */
    final public function validate(Request $request, string $secret, array $options = []): bool
    {
        // Get the auth header
        $authHeader = $this->getSignatureFromRequest($request, $options);

        if (! $authHeader) {
            return false;
        }

        // Remove "Basic " prefix
        if (str_starts_with($authHeader, 'Basic ')) {
            $authHeader = substr($authHeader, 6);
        }

        // Decode Base64
        $credentials = base64_decode($authHeader);

        // Compare with expected credentials
        return hash_equals($secret, $credentials);
    }

    /**
     * Generate a Basic Auth header value.
     *
     * @param array|string $payload Not used for Basic Auth
     * @param string       $secret  The credentials in "username:password" format
     * @param array        $options Generation options
     *
     * @return string The generated Authorization header value
     */
    final public function generate(array|string $payload, string $secret, array $options = []): string
    {
        // Encode the credentials
        $encoded = base64_encode($secret);

        // Return with "Basic " prefix
        return 'Basic ' . $encoded;
    }

    /**
     * Get the Authorization header from a request.
     *
     * @param Request $request The HTTP request
     * @param array   $options Options including header name
     *
     * @return string|null The header value or null if not found
     */
    final public function getSignatureFromRequest(Request $request, array $options = []): ?string
    {
        $headerName = $this->getSignatureHeaderName($options);

        if (! $request->hasHeader($headerName)) {
            return null;
        }

        return $request->header($headerName);
    }

    /**
     * Get the name of the Authorization header.
     *
     * @param array $options Options that may include a custom header name
     *
     * @return string The header name
     */
    final public function getSignatureHeaderName(array $options = []): string
    {
        return $options['auth_header'] ?? 'Authorization';
    }

    /**
     * Get the algorithm used for signatures.
     *
     * Not applicable for Basic Auth, but required by interface.
     *
     * @param array $options Options that may include a custom algorithm
     *
     * @return string Always returns "basic"
     */
    final public function getAlgorithm(array $options = []): string
    {
        return 'basic';
    }
}
