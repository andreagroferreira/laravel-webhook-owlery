<?php

namespace WizardingCode\WebhookOwlery\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class WebhookRateLimiter
{
    /**
     * The rate limiter instance.
     */
    protected RateLimiter $limiter;

    /**
     * Create a new rate limiter middleware.
     *
     * @return void
     */
    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ?string $provider = null, ?int $maxAttempts = null, ?int $decayMinutes = null): Response
    {
        // Get rate limit configuration
        $enabled = config('webhook-owlery.security.rate_limiting.enabled', true);

        // Skip rate limiting if disabled
        if (! $enabled) {
            return $next($request);
        }

        // Determine the rate limit key
        $key = $this->resolveRequestSignature($request, $provider);

        // Get rate limit parameters
        $maxAttempts = $maxAttempts ?? $this->resolveMaxAttempts($request, $provider);
        $decayMinutes = $decayMinutes ?? $this->resolveDecayMinutes($provider);

        // If no rate limit is set, continue
        if ($maxAttempts <= 0) {
            return $next($request);
        }

        // Increment the rate limiter
        $tooManyAttempts = $this->limiter->tooManyAttempts($key, $maxAttempts);

        if ($tooManyAttempts) {
            Log::warning('Webhook rate limit exceeded', [
                'provider' => $provider,
                'ip' => $request->ip(),
                'key' => $key,
                'max_attempts' => $maxAttempts,
                'decay_minutes' => $decayMinutes,
            ]);

            return $this->buildResponse($key, $maxAttempts);
        }

        // Increment the counter
        $this->limiter->hit($key, $decayMinutes * 60);

        $response = $next($request);

        // Add rate limit headers to response
        return $this->addHeaders(
            $response,
            $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts)
        );
    }

    /**
     * Resolve the signature for the rate limiter.
     */
    protected function resolveRequestSignature(Request $request, ?string $provider): string
    {
        // Use IP address and provider as the key
        $signature = $request->ip();

        // Add provider if available
        if ($provider) {
            $signature .= '|' . $provider;
        }

        // Add custom scope from configuration if available
        $customScope = config('webhook-owlery.security.rate_limiting.scope');
        if ($customScope && is_callable($customScope)) {
            $additionalScope = $customScope($request, $provider);
            if ($additionalScope) {
                $signature .= '|' . $additionalScope;
            }
        }

        // Add a prefix to avoid conflicts with other rate limiters
        return 'webhook-owlery|' . $signature;
    }

    /**
     * Resolve the maximum number of attempts.
     */
    protected function resolveMaxAttempts(Request $request, ?string $provider): int
    {
        // Check for provider-specific limit first
        if ($provider) {
            $providerLimit = config("webhook-owlery.providers.{$provider}.rate_limit");
            if ($providerLimit) {
                return (int) $providerLimit;
            }
        }

        // Fall back to default rate limit
        return (int) config('webhook-owlery.security.rate_limiting.max_attempts', 60);
    }

    /**
     * Resolve the decay minutes.
     */
    protected function resolveDecayMinutes(?string $provider): int
    {
        // Check for provider-specific decay first
        if ($provider) {
            $providerDecay = config("webhook-owlery.providers.{$provider}.rate_limit_decay_minutes");
            if ($providerDecay) {
                return (int) $providerDecay;
            }
        }

        // Fall back to default decay
        return (int) config('webhook-owlery.security.rate_limiting.decay_minutes', 1);
    }

    /**
     * Create a rate limited response.
     */
    protected function buildResponse(string $key, int $maxAttempts): Response
    {
        $response = response()->json([
            'message' => 'Too many webhook requests. Please try again later.',
            'success' => false,
        ], 429);

        $retryAfter = $this->limiter->availableIn($key);

        return $this->addHeaders(
            $response,
            $maxAttempts,
            0,
            $retryAfter
        );
    }

    /**
     * Add the rate limit headers to the response.
     */
    protected function addHeaders(Response $response, int $maxAttempts, int $remainingAttempts, ?int $retryAfter = null): Response
    {
        $headers = [
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $remainingAttempts,
        ];

        if ($retryAfter) {
            $headers['Retry-After'] = $retryAfter;
            $headers['X-RateLimit-Reset'] = $this->availableAt($retryAfter);
        }

        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }

    /**
     * Calculate the number of remaining attempts.
     */
    protected function calculateRemainingAttempts(string $key, int $maxAttempts, ?int $retryAfter = null): int
    {
        if ($retryAfter) {
            return 0;
        }

        return $this->limiter->retriesLeft($key, $maxAttempts);
    }

    /**
     * Get the timestamp for when a rate limit will be available.
     */
    protected function availableAt(int $retryAfter): int
    {
        return time() + $retryAfter;
    }
}
