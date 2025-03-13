<?php

namespace WizardingCode\WebhookOwlery\Validators;

use Exception;
use Illuminate\Http\Request;
use WizardingCode\WebhookOwlery\Contracts\SignatureValidatorContract;

class JwtSignatureValidator implements SignatureValidatorContract
{
    /**
     * Validate a webhook using JWT.
     *
     * @param Request $request The HTTP request
     * @param string  $secret  The secret key
     * @param array   $options Validation options
     *
     * @return bool Whether the signature is valid
     */
    final public function validate(Request $request, string $secret, array $options = []): bool
    {
        // Get the JWT token from the request
        $token = $this->getSignatureFromRequest($request, $options);

        if (! $token) {
            return false;
        }

        try {
            // Split the token into its parts
            $parts = explode('.', $token);

            if (count($parts) !== 3) {
                return false;
            }

            [$header, $payload, $signature] = $parts;

            // Verify the signature
            $data = $header . '.' . $payload;
            $algorithm = $this->getJwtAlgorithm($header);

            // Generate expected signature
            $expectedSignature = $this->generateJwtSignature($data, $secret, $algorithm);

            // URL-safe base64 decode the signature
            $signature = $this->base64UrlDecode($signature);

            // Compare signatures
            return hash_equals($expectedSignature, $signature);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Generate a JWT token.
     *
     * @param array|string $payload The payload to include in the token
     * @param string       $secret  The secret key
     * @param array        $options JWT generation options
     *
     * @throws Exception
     *
     * @return string The generated JWT token
     */
    final public function generate(array|string $payload, string $secret, array $options = []): string
    {
        // Convert payload to array if it's a string
        if (is_string($payload)) {
            $payload = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $payload = ['data' => $payload];
            }
        }

        // Merge with default claims
        $claims = array_merge([
            'iat' => time(),
            'exp' => time() + 3600, // 1 hour expiration
            'iss' => $options['issuer'] ?? 'webhook-owlery',
        ], $payload);

        // Algorithm to use
        $algorithm = $options['algorithm'] ?? $this->getAlgorithm();

        // Create JWT parts
        $header = [
            'alg' => $algorithm,
            'typ' => 'JWT',
        ];

        $headerEncoded = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $payloadEncoded = $this->base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));

        $data = $headerEncoded . '.' . $payloadEncoded;
        $signature = $this->generateJwtSignature($data, $secret, $algorithm);

        // Encode signature
        $signatureEncoded = $this->base64UrlEncode($signature);

        // Combine parts
        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }

    /**
     * Get the JWT token from a request.
     *
     * @param Request $request The HTTP request
     * @param array   $options Options including header name
     *
     * @return string|null The JWT token or null if not found
     */
    final public function getSignatureFromRequest(Request $request, array $options = []): ?string
    {
        $headerName = $this->getSignatureHeaderName($options);

        // Check if token is in header
        if ($request->hasHeader($headerName)) {
            $header = $request->header($headerName);

            // If token is in Authorization header with Bearer prefix
            if ($headerName === 'Authorization' && str_starts_with($header, 'Bearer ')) {
                return substr($header, 7);
            }

            return $header;
        }

        // Check if token is in query string
        $queryParam = $options['query_param'] ?? 'token';
        if ($request->has($queryParam)) {
            return $request->input($queryParam);
        }

        return null;
    }

    /**
     * Get the name of the JWT header.
     *
     * @param array $options Options that may include a custom header name
     *
     * @return string The header name
     */
    final public function getSignatureHeaderName(array $options = []): string
    {
        return $options['jwt_header'] ?? 'Authorization';
    }

    /**
     * Get the algorithm used for JWT signatures.
     *
     * @param array $options Options that may include a custom algorithm
     *
     * @return string The algorithm (e.g., 'HS256')
     */
    final public function getAlgorithm(array $options = []): string
    {
        return $options['algorithm'] ?? 'HS256';
    }

    /**
     * Decode the JWT header to determine the algorithm.
     *
     * @param string $headerEncoded The base64url encoded header
     *
     * @throws Exception If header cannot be decoded
     *
     * @return string The algorithm
     */
    private function getJwtAlgorithm(string $headerEncoded): string
    {
        $headerJson = $this->base64UrlDecode($headerEncoded);
        $header = json_decode($headerJson, true, 512, JSON_THROW_ON_ERROR);

        if (json_last_error() !== JSON_ERROR_NONE || ! isset($header['alg'])) {
            throw new Exception('Invalid JWT header');
        }

        return $header['alg'];
    }

    /**
     * Generate the JWT signature.
     *
     * @param string $data      The data to sign (header.payload)
     * @param string $secret    The secret key
     * @param string $algorithm The algorithm to use
     *
     * @throws Exception If algorithm is not supported
     *
     * @return string The raw signature
     */
    private function generateJwtSignature(string $data, string $secret, string $algorithm): string
    {
        return match ($algorithm) {
            'HS256' => hash_hmac('sha256', $data, $secret, true),
            'HS384' => hash_hmac('sha384', $data, $secret, true),
            'HS512' => hash_hmac('sha512', $data, $secret, true),
            default => throw new Exception('Unsupported JWT algorithm: ' . $algorithm),
        };
    }

    /**
     * Encode a string with URL-safe base64.
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Decode a URL-safe base64 string.
     */
    private function base64UrlDecode(string $data): string
    {
        $padding = strlen($data) % 4;
        if ($padding > 0) {
            $data .= str_repeat('=', 4 - $padding);
        }

        return base64_decode(strtr($data, '-_', '+/'));
    }
}
