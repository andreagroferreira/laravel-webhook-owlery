<?php

namespace WizardingCode\WebhookOwlery\Contracts;

use Illuminate\Http\Request;

interface SignatureValidatorContract
{
    /**
     * Validate a webhook signature.
     *
     * @param Request $request The HTTP request
     * @param string  $secret  The secret key
     * @param array   $options Validation options
     *
     * @return bool Whether the signature is valid
     */
    public function validate(Request $request, string $secret, array $options = []): bool;

    /**
     * Generate a signature for an outgoing webhook.
     *
     * @param array|string $payload The payload to sign
     * @param string       $secret  The secret key
     * @param array        $options Signature options
     *
     * @return string The generated signature
     */
    public function generate(array|string $payload, string $secret, array $options = []): string;

    /**
     * Get the signature from a request.
     *
     * @param Request $request The HTTP request
     * @param array   $options Options including header name
     *
     * @return string|null The signature or null if not found
     */
    public function getSignatureFromRequest(Request $request, array $options = []): ?string;

    /**
     * Get the name of the signature header.
     *
     * @param array $options Options that may include a custom header name
     *
     * @return string The header name
     */
    public function getSignatureHeaderName(array $options = []): string;

    /**
     * Get the algorithm used for signatures.
     *
     * @param array $options Options that may include a custom algorithm
     *
     * @return string The algorithm (e.g., 'sha256')
     */
    public function getAlgorithm(array $options = []): string;
}
