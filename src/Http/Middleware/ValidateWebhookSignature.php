<?php

namespace WizardingCode\WebhookOwlery\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use WizardingCode\WebhookOwlery\Contracts\SignatureValidatorContract;
use WizardingCode\WebhookOwlery\Events\WebhookInvalid;
use WizardingCode\WebhookOwlery\Exceptions\InvalidSignatureException;
use WizardingCode\WebhookOwlery\Validators\ProviderSpecificValidator;

class ValidateWebhookSignature
{
    /**
     * The signature validator instance.
     */
    private SignatureValidatorContract $validator;

    /**
     * Create a new middleware instance.
     *
     * @return void
     */
    public function __construct(SignatureValidatorContract $validator)
    {
        $this->validator = $validator;
    }

    /**
     * Handle an incoming request.
     *
     * @param string|null $provider The webhook provider to validate (e.g., 'stripe', 'github')
     */
    final public function handle(Request $request, Closure $next, ?string $provider = null): Response
    {
        try {
            // If a provider is specified, use a provider-specific validator
            $validator = $this->getValidator($provider);

            // Get the secret key for this provider
            $secret = $this->getSecretKey($provider);

            if (empty($secret)) {
                // If no secret is available and signatures are required, reject
                if (config('webhook-owlery.security.require_signatures', true)) {
                    Log::warning('Webhook signature validation failed: No secret key available', [
                        'provider' => $provider,
                        'ip' => $request->ip(),
                    ]);

                    // Fire invalid signature event
                    event(new WebhookInvalid(
                        $provider ?? 'unknown',
                        $request->header('X-Webhook-Event') ?? 'unknown',
                        $request,
                        'No secret key available for validation'
                    ));

                    return response()->json([
                        'message' => 'Webhook signature validation failed: No secret key configured',
                        'success' => false,
                    ], 401);
                }

                // If signatures are not required, allow the request through
                Log::warning('Proceeding without webhook signature validation', [
                    'provider' => $provider,
                    'ip' => $request->ip(),
                ]);

                return $next($request);
            }

            // Get validation options
            $options = $this->getValidationOptions($provider);

            // Validate the signature
            if (! $validator->validate($request, $secret, $options)) {
                Log::warning('Webhook signature validation failed', [
                    'provider' => $provider,
                    'ip' => $request->ip(),
                ]);

                // Fire invalid signature event
                event(new WebhookInvalid(
                    $provider ?? 'unknown',
                    $request->header('X-Webhook-Event') ?? 'unknown',
                    $request,
                    'Invalid signature'
                ));

                return response()->json([
                    'message' => 'Webhook signature validation failed',
                    'success' => false,
                ], 401);
            }

            // Signature is valid, continue
            return $next($request);
        } catch (InvalidSignatureException $e) {
            Log::warning('Webhook signature validation error', [
                'provider' => $provider,
                'ip' => $request->ip(),
                'error' => $e->getMessage(),
            ]);

            // Fire invalid signature event
            event(new WebhookInvalid(
                $provider ?? 'unknown',
                $request->header('X-Webhook-Event') ?? 'unknown',
                $request,
                $e->getMessage()
            ));

            return response()->json([
                'message' => 'Webhook signature validation error: ' . $e->getMessage(),
                'success' => false,
            ], 401);
        } catch (\Throwable $e) {
            Log::error('Unexpected error during webhook signature validation', [
                'provider' => $provider,
                'ip' => $request->ip(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error processing webhook',
                'success' => false,
            ], 500);
        }
    }

    /**
     * Get the appropriate validator for the provider.
     */
    private function getValidator(?string $provider): SignatureValidatorContract
    {
        if ($provider) {
            // Check if we have a provider-specific validator
            $providerValidators = [
                'stripe', 'github', 'shopify', 'paypal', 'slack',
            ];

            if (in_array($provider, $providerValidators)) {
                return new ProviderSpecificValidator($provider);
            }

            // Check if a custom validator is registered for this provider
            $validatorClass = config("webhook-owlery.providers.{$provider}.validator");
            if ($validatorClass && class_exists($validatorClass)) {
                return new $validatorClass;
            }
        }

        // Use the default validator
        return $this->validator;
    }

    /**
     * Get the secret key for the provider.
     */
    private function getSecretKey(?string $provider): ?string
    {
        if ($provider) {
            // Try to get provider-specific secret key
            $secret = config("webhook-owlery.providers.{$provider}.secret_key");
            if ($secret) {
                return $secret;
            }
        }

        // Fall back to default secret key
        return config('webhook-owlery.security.default_signature_key');
    }

    /**
     * Get validation options for the provider.
     */
    private function getValidationOptions(?string $provider): array
    {
        $options = [];

        if ($provider) {
            // Get provider-specific options
            $providerOptions = config("webhook-owlery.providers.{$provider}", []);

            // Extract relevant options
            $relevantOptions = [
                'signature_header',
                'signature_prefix',
                'timestamp_header',
                'timestamp_required',
                'timestamp_tolerance',
                'algorithm',
            ];

            foreach ($relevantOptions as $option) {
                if (isset($providerOptions[$option])) {
                    $options[$option] = $providerOptions[$option];
                }
            }
        }

        // Add global options
        $options['tolerance'] = config('webhook-owlery.security.timestamp_tolerance', 300);

        return $options;
    }
}
