<?php

namespace WizardingCode\WebhookOwlery\Validators;

use Illuminate\Http\Request;
use JsonException;
use WizardingCode\WebhookOwlery\Contracts\SignatureValidatorContract;

class HmacSignatureValidator implements SignatureValidatorContract
{
    /**
     * The header name to get the signature from.
     *
     * @var string
     */
    protected $headerName = 'X-Signature';

    /**
     * The algorithm to use for HMAC.
     *
     * @var string
     */
    protected $algorithm = 'sha256';

    /**
     * Constructor for the validator.
     *
     * @param string|null $headerName Optional custom header name
     * @param string|null $algorithm  Optional custom algorithm
     */
    public function __construct(?string $headerName = null, ?string $algorithm = null)
    {
        if ($headerName !== null) {
            $this->headerName = $headerName;
        }

        if ($algorithm !== null) {
            $this->algorithm = $algorithm;
        }
    }

    /**
     * Validate a webhook signature.
     *
     * @param Request $request The HTTP request
     * @param string  $secret  The secret key
     * @param array   $options Validation options
     *
     * @return bool Whether the signature is valid
     */
    final public function validate(Request $request, string $secret, array $options = []): bool
    {
        // Get the signature from the request
        $signature = $this->getSignatureFromRequest($request, $options);

        if (! $signature) {
            return false;
        }

        // Get the payload
        $payload = $this->getPayloadForSigning($request, $options);

        // Get the algorithm to use
        $algorithm = $this->getAlgorithm($options);

        // Generate the expected signature
        $expectedSignature = $this->generate($payload, $secret, [
            'algorithm' => $algorithm,
            'prefix' => $options['prefix'] ?? null,
        ]);

        // Compare the signatures using a time-constant comparison function to prevent timing attacks
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Generate a signature for an outgoing webhook.
     *
     * @param array|string $payload The payload to sign
     * @param string       $secret  The secret key
     * @param array        $options Signature options
     *
     * @throws JsonException
     *
     * @return string The generated signature
     */
    final public function generate(array|string $payload, string $secret, array $options = []): string
    {
        // Convert payload to string if it's an array
        if (is_array($payload)) {
            $payload = json_encode($payload, JSON_THROW_ON_ERROR);
        }

        // Get algorithm
        $algorithm = $options['algorithm'] ?? $this->getAlgorithm();

        // Generate signature
        $signature = hash_hmac($algorithm, $payload, $secret);

        // Add prefix if specified
        if (isset($options['prefix'])) {
            $signature = $options['prefix'] . $signature;
        }

        return $signature;
    }

    /**
     * Get the signature from a request.
     *
     * @param Request $request The HTTP request
     * @param array   $options Options including header name
     *
     * @return string|null The signature or null if not found
     */
    final public function getSignatureFromRequest(Request $request, array $options = []): ?string
    {
        // Get the header name
        $headerName = $this->getSignatureHeaderName($options);

        // Check if the header exists
        if (! $request->hasHeader($headerName)) {
            return null;
        }

        $signatureHeader = $request->header($headerName);

        // Check if we need to extract the signature from a formatted string
        if (isset($options['signature_format'])) {
            return $this->extractSignatureFromFormat($signatureHeader, $options['signature_format']);
        }

        // Check if we need to remove a prefix
        if (isset($options['prefix']) && str_starts_with($signatureHeader, $options['prefix'])) {
            return substr($signatureHeader, strlen($options['prefix']));
        }

        return $signatureHeader;
    }

    /**
     * Get the name of the signature header.
     *
     * @param array $options Options that may include a custom header name
     *
     * @return string The header name
     */
    final public function getSignatureHeaderName(array $options = []): string
    {
        return $options['signature_header'] ??
            $this->headerName ??
            config('webhook-owlery.receiving.signature_header', 'X-Webhook-Signature');
    }

    /**
     * Get the algorithm used for signatures.
     *
     * @param array $options Options that may include a custom algorithm
     *
     * @return string The algorithm (e.g., 'sha256')
     */
    final public function getAlgorithm(array $options = []): string
    {
        return $options['algorithm'] ?? $this->algorithm ?? 'sha256';
    }

    /**
     * Get the payload to use for signature validation.
     *
     * @param Request $request The HTTP request
     * @param array   $options Options for payload extraction
     *
     * @return string The payload as a string
     */
    private function getPayloadForSigning(Request $request, array $options = []): string
    {
        // If timestamp is included in signature calculation
        if (isset($options['timestamp_header']) && $request->hasHeader($options['timestamp_header'])) {
            $timestamp = $request->header($options['timestamp_header']);
            $rawPayload = $request->getContent();

            // The payload is timestamp + . + raw body
            return $timestamp . '.' . $rawPayload;
        }

        // Default to raw request body
        return $request->getContent();
    }

    /**
     * Extract signature from a formatted header value.
     *
     * @param string $headerValue The raw header value
     * @param string $format      The format to parse
     *
     * @return string|null The extracted signature
     */
    private function extractSignatureFromFormat(string $headerValue, string $format): ?string
    {
        // Handle Stripe-style format: t=timestamp,v1=signature
        if ($format === 'stripe') {
            $parts = explode(',', $headerValue);
            foreach ($parts as $part) {
                if (str_starts_with($part, 'v1=')) {
                    return substr($part, 3);
                }
            }

            return null;
        }

        // Handle GitHub-style format: sha256=signature
        if ($format === 'github') {
            if (str_starts_with($headerValue, 'sha256=')) {
                return substr($headerValue, 7);
            }

            return null;
        }

        // Default to returning the header value as-is
        return $headerValue;
    }
}
