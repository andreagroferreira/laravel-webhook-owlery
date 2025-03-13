<?php

namespace WizardingCode\WebhookOwlery\Validators;

use Illuminate\Http\Request;
use InvalidArgumentException;
use WizardingCode\WebhookOwlery\Contracts\SignatureValidatorContract;

class ProviderSpecificValidator implements SignatureValidatorContract
{
    /**
     * The provider to validate for.
     */
    private string $provider;

    /**
     * The validators for each provider.
     */
    private array $validators = [
        'stripe' => HmacSignatureValidator::class,
        'github' => HmacSignatureValidator::class,
        'shopify' => HmacSignatureValidator::class,
        'paypal' => HmacSignatureValidator::class,
        'slack' => HmacSignatureValidator::class,
    ];

    /**
     * Create a new provider-specific validator.
     *
     * @throws InvalidArgumentException
     */
    public function __construct(string $provider = 'stripe')
    {
        $this->provider = strtolower($provider);

        if (! array_key_exists($this->provider, $this->validators)) {
            throw new InvalidArgumentException("Validator for provider '{$provider}' not found");
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
        $options = $this->prepareOptions($options);

        return $this->getValidator()->validate($request, $secret, $options);
    }

    /**
     * Generate a signature for an outgoing webhook.
     *
     * @param array|string $payload The payload to sign
     * @param string       $secret  The secret key
     * @param array        $options Signature options
     *
     * @return string The generated signature
     */
    final public function generate(array|string $payload, string $secret, array $options = []): string
    {
        $options = $this->prepareOptions($options);

        return $this->getValidator()->generate($payload, $secret, $options);
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
        $options = $this->prepareOptions($options);

        return $this->getValidator()->getSignatureFromRequest($request, $options);
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
        $options = $this->prepareOptions($options);

        return $this->getValidator()->getSignatureHeaderName($options);
    }

    /**
     * Get the algorithm used for signatures.
     *
     * @param array $options Options that may include a custom algorithm
     *
     * @return string The algorithm
     */
    final public function getAlgorithm(array $options = []): string
    {
        $options = $this->prepareOptions($options);

        return $this->getValidator()->getAlgorithm($options);
    }

    /**
     * Get the validator instance for the current provider.
     */
    private function getValidator(): SignatureValidatorContract
    {
        $validatorClass = $this->validators[$this->provider];

        return app($validatorClass);
    }

    /**
     * Prepare provider-specific options.
     */
    private function prepareOptions(array $options): array
    {
        return match ($this->provider) {
            'stripe' => $this->prepareStripeOptions($options),
            'github' => $this->prepareGithubOptions($options),
            'shopify' => $this->prepareShopifyOptions($options),
            'paypal' => $this->preparePaypalOptions($options),
            'slack' => $this->prepareSlackOptions($options),
            default => $options,
        };
    }

    /**
     * Prepare options for Stripe webhooks.
     */
    private function prepareStripeOptions(array $options): array
    {
        return array_merge([
            'signature_header' => 'Stripe-Signature',
            'timestamp_header' => 't',
            'signature_format' => 'stripe',
        ], $options);
    }

    /**
     * Prepare options for GitHub webhooks.
     */
    private function prepareGithubOptions(array $options): array
    {
        return array_merge([
            'signature_header' => 'X-Hub-Signature-256',
            'signature_format' => 'github',
            'algorithm' => 'sha256',
        ], $options);
    }

    /**
     * Prepare options for Shopify webhooks.
     */
    private function prepareShopifyOptions(array $options): array
    {
        return array_merge([
            'signature_header' => 'X-Shopify-Hmac-Sha256',
            'algorithm' => 'sha256',
        ], $options);
    }

    /**
     * Prepare options for PayPal webhooks.
     */
    private function preparePaypalOptions(array $options): array
    {
        // PayPal uses a more complex verification process
        // They send event_id, crc32, and transmission_sig in separate headers
        return array_merge([
            'signature_header' => 'Paypal-Transmission-Sig',
            'id_header' => 'Paypal-Transmission-Id',
            'timestamp_header' => 'Paypal-Transmission-Time',
        ], $options);
    }

    /**
     * Prepare options for Slack webhooks.
     */
    private function prepareSlackOptions(array $options): array
    {
        // Slack uses a simple verification token or signing secret
        return array_merge([
            'signature_header' => 'X-Slack-Signature',
            'timestamp_header' => 'X-Slack-Request-Timestamp',
            'signature_format' => 'v0=',
            'prefix' => 'v0=',
        ], $options);
    }
}
