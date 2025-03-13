<?php

namespace WizardingCode\WebhookOwlery\Services;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use WizardingCode\WebhookOwlery\Contracts\SignatureValidatorContract;
use WizardingCode\WebhookOwlery\Contracts\WebhookReceiverContract;
use WizardingCode\WebhookOwlery\Contracts\WebhookRepositoryContract;
use WizardingCode\WebhookOwlery\Events\WebhookFailed;
use WizardingCode\WebhookOwlery\Events\WebhookHandled;
use WizardingCode\WebhookOwlery\Events\WebhookInvalid;
use WizardingCode\WebhookOwlery\Events\WebhookReceived;
use WizardingCode\WebhookOwlery\Events\WebhookValidated;
use WizardingCode\WebhookOwlery\Exceptions\InvalidSignatureException;
use WizardingCode\WebhookOwlery\Exceptions\WebhookProcessingException;
use WizardingCode\WebhookOwlery\Jobs\ProcessIncomingWebhook;
use WizardingCode\WebhookOwlery\Models\WebhookEvent;
use WizardingCode\WebhookOwlery\Support\WebhookUtils;
use WizardingCode\WebhookOwlery\Validators\HmacSignatureValidator;
use WizardingCode\WebhookOwlery\Validators\ProviderSpecificValidator;

class WebhookReceiver implements WebhookReceiverContract
{
    /**
     * The webhook repository implementation.
     */
    private WebhookRepositoryContract $repository;

    /**
     * The signature validator implementation.
     */
    private SignatureValidatorContract $validator;

    /**
     * Registered event handlers.
     */
    private array $handlers = [];

    /**
     * Registered before hooks.
     */
    private array $beforeHooks = [];

    /**
     * Registered after hooks.
     */
    private array $afterHooks = [];

    /**
     * Create a new webhook receiver instance.
     *
     * @return void
     */
    public function __construct(
        WebhookRepositoryContract $repository,
        ?SignatureValidatorContract $validator = null
    ) {
        $this->repository = $repository;
        $this->validator = $validator ?? new HmacSignatureValidator;
    }

    /**
     * Configure a webhook endpoint for receiving webhooks.
     *
     * @param string $source   The source/provider name (e.g., 'stripe', 'github')
     * @param string $endpoint The endpoint path
     * @param array  $options  Configuration options
     */
    final public function configureEndpoint(string $source, string $endpoint, array $options = []): mixed
    {
        // This could be extended to register routes dynamically,
        // but for now we'll assume routes are registered via the package's routes file

        // Set up handlers if provided
        if (isset($options['handlers']) && is_array($options['handlers'])) {
            foreach ($options['handlers'] as $event => $handler) {
                $this->on($source, $event, $handler);
            }
        }

        // Set up a catch-all handler if provided
        if (isset($options['handler']) && is_callable($options['handler'])) {
            $this->onAny($source, $options['handler']);
        }

        // Set up before and after hooks if provided
        if (isset($options['before']) && is_callable($options['before'])) {
            $this->before($source, $options['before']);
        }

        if (isset($options['after']) && is_callable($options['after'])) {
            $this->after($source, $options['after']);
        }

        return true;
    }

    /**
     * Handle an incoming webhook request.
     *
     * @param string  $source  The source/provider name
     * @param Request $request The HTTP request
     */
    final public function handleRequest(string $source, Request $request): mixed
    {
        // Normalize source name
        $source = strtolower($source);

        // Fire webhook received event
        Event::dispatch(new WebhookReceived($source, $request));

        try {
            // Log basic info about the incoming webhook
            Log::debug("Webhook received from {$source}", [
                'ip' => $request->ip(),
                'method' => $request->method(),
                'content_type' => $request->header('Content-Type'),
            ]);

            // Run before hooks
            $this->runBeforeHooks($source, $request);

            // Extract the webhook event type
            $event = WebhookUtils::extractEventName($request, $source, [
                'event_header' => config('webhook-owlery.receiving.event_header'),
            ]);

            if (! $event) {
                $event = 'unknown';
            }

            // Verify the webhook signature if required
            $isValid = true;
            $validationMessage = null;

            if (config('webhook-owlery.receiving.verify_signatures', true)) {
                try {
                    $this->verifySignature($source, $request);
                    Event::dispatch(new WebhookValidated($source, $event, $request));
                } catch (InvalidSignatureException $e) {
                    $isValid = false;
                    $validationMessage = $e->getMessage();

                    // Decide whether to continue processing based on config
                    if (config('webhook-owlery.receiving.require_valid_signature', true)) {
                        Event::dispatch(new WebhookInvalid($source, $event, $request, $validationMessage));
                        Log::warning("Invalid webhook signature from {$source}", [
                            'error' => $validationMessage,
                        ]);

                        // Store the event with invalid status
                        $webhookEvent = $this->storeWebhookEvent($source, $event, $request, false, $validationMessage);

                        return response()->json([
                            'message' => 'Invalid signature',
                            'success' => false,
                        ], Response::HTTP_UNAUTHORIZED);
                    }

                    // Log but continue processing
                    Log::warning("Invalid webhook signature from {$source}, continuing anyway", [
                        'error' => $validationMessage,
                    ]);
                }
            }

            // Store the webhook event
            $webhookEvent = $this->storeWebhookEvent($source, $event, $request, $isValid, $validationMessage);

            // Process the webhook either synchronously or asynchronously
            if (config('webhook-owlery.receiving.process_async', true)) {
                // Queue the job to process the webhook
                ProcessIncomingWebhook::dispatch($webhookEvent)
                    ->onQueue(config('webhook-owlery.receiving.queue', 'default'));

                return response()->json([
                    'message' => 'Webhook received and queued for processing',
                    'success' => true,
                    'webhook_id' => $webhookEvent->uuid,
                ]);
            }

            // Process synchronously
            return $this->processWebhookEvent($webhookEvent);
        } catch (\Throwable $e) {
            // Log the error
            Log::error("Error handling webhook from {$source}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            Event::dispatch(new WebhookFailed($source, $request, $e));

            // Return an appropriate response
            return response()->json([
                'message' => 'Error processing webhook',
                'success' => false,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Process a stored webhook event.
     *
     * @param WebhookEvent $webhookEvent The webhook event to process
     */
    final public function processWebhookEvent(WebhookEvent $webhookEvent): Response|JsonResponse
    {
        $source = $webhookEvent->source;
        $event = $webhookEvent->event;
        $startTime = microtime(true);

        try {
            // Find a handler for this event
            $handler = $this->getHandler($source, $event);

            if (! $handler) {
                // No handler found, mark as processed but note the issue
                $webhookEvent->markAsProcessed('skipped', 'No handler registered for this event');

                return response()->json([
                    'message' => 'Webhook received but no handler found',
                    'success' => true,
                    'webhook_id' => $webhookEvent->uuid,
                ]);
            }

            // Execute the handler
            $result = $handler($webhookEvent->payload, $webhookEvent);

            // Calculate processing time
            $processingTime = round((microtime(true) - $startTime) * 1000);

            // Mark the event as processed
            $webhookEvent->markAsProcessed(
                'success',
                null,
                $processingTime,
                get_class($handler)
            );

            // Run after hooks
            $this->runAfterHooks($source, $webhookEvent, $result);

            // Fire event
            Event::dispatch(new WebhookHandled($webhookEvent, $result));

            // Return appropriate response
            if (is_object($result) && method_exists($result, 'toResponse')) {
                return $result->toResponse(request());
            }

            return response()->json([
                'message' => 'Webhook processed successfully',
                'success' => true,
                'webhook_id' => $webhookEvent->uuid,
            ]);
        } catch (\Throwable $e) {
            // Calculate processing time
            $processingTime = round((microtime(true) - $startTime) * 1000);

            // Mark the event as processed with error
            $webhookEvent->markAsProcessed(
                'error',
                $e->getMessage(),
                $processingTime,
                get_class($e)
            );

            // Fire event
            Event::dispatch(new WebhookFailed($source, request(), $e, $webhookEvent));

            // Log the error
            Log::error("Error processing webhook {$webhookEvent->uuid}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'source' => $source,
                'event' => $event,
            ]);

            // Return error response
            return response()->json([
                'message' => 'Error processing webhook',
                'success' => false,
                'webhook_id' => $webhookEvent->uuid,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Register a handler for a specific webhook event.
     *
     * @param string           $source  The source/provider name
     * @param string           $event   The event name or pattern
     * @param callable|Closure $handler The handler function
     */
    final public function on(string $source, string $event, callable|Closure $handler): mixed
    {
        $source = strtolower($source);

        if (! isset($this->handlers[$source])) {
            $this->handlers[$source] = [];
        }

        $this->handlers[$source][$event] = $handler;

        return $this;
    }

    /**
     * Register a handler for all events from a source.
     *
     * @param string           $source  The source/provider name
     * @param callable|Closure $handler The handler function
     */
    final public function onAny(string $source, callable|Closure $handler): mixed
    {
        return $this->on($source, '*', $handler);
    }

    /**
     * Register a handler that runs before webhook processing.
     *
     * @param string           $source  The source/provider name
     * @param callable|Closure $handler The handler function
     */
    final public function before(string $source, callable|Closure $handler): mixed
    {
        $source = strtolower($source);

        if (! isset($this->beforeHooks[$source])) {
            $this->beforeHooks[$source] = [];
        }

        $this->beforeHooks[$source][] = $handler;

        return $this;
    }

    /**
     * Register a handler that runs after webhook processing.
     *
     * @param string           $source  The source/provider name
     * @param callable|Closure $handler The handler function
     */
    final public function after(string $source, callable|Closure $handler): mixed
    {
        $source = strtolower($source);

        if (! isset($this->afterHooks[$source])) {
            $this->afterHooks[$source] = [];
        }

        $this->afterHooks[$source][] = $handler;

        return $this;
    }

    /**
     * Verify if a webhook is valid based on its signature.
     *
     * @param string  $source  The source/provider name
     * @param Request $request The HTTP request
     *
     * @throws InvalidSignatureException
     */
    final public function verifySignature(string $source, Request $request): bool
    {
        // Get the secret for this source
        $secret = $this->getSecretForSource($source);

        if (empty($secret)) {
            if (config('webhook-owlery.receiving.require_signature_secret', true)) {
                throw new InvalidSignatureException($source, null, 'No secret configured for this source');
            }

            return true;
        }

        // Get the appropriate validator for this source
        $validator = $this->getValidatorForSource($source);

        // Get any provider-specific options
        $options = $this->getOptionsForSource($source);

        // Verify the signature
        $isValid = $validator->validate($request, $secret, $options);

        if (! $isValid) {
            throw new InvalidSignatureException(
                $source,
                $validator->getSignatureFromRequest($request, $options),
                'Invalid webhook signature'
            );
        }

        return true;
    }

    /**
     * Get a registered handler for a specific event.
     *
     * @param string $source The source/provider name
     * @param string $event  The event name
     */
    final public function getHandler(string $source, string $event): ?callable
    {
        $source = strtolower($source);

        if (! isset($this->handlers[$source])) {
            return null;
        }

        // Check for exact match
        if (isset($this->handlers[$source][$event])) {
            return $this->handlers[$source][$event];
        }

        // Check for wildcard handler
        if (isset($this->handlers[$source]['*'])) {
            return $this->handlers[$source]['*'];
        }

        // Check for wildcard patterns (e.g., "customer.*" would match "customer.created")
        foreach ($this->handlers[$source] as $pattern => $handler) {
            if (str_ends_with($pattern, '*') && str_starts_with($event, substr($pattern, 0, -1))) {
                return $handler;
            }
        }

        return null;
    }

    /**
     * Store a webhook event in the repository.
     */
    private function storeWebhookEvent(
        string $source,
        string $event,
        Request $request,
        bool $isValid = true,
        ?string $validationMessage = null
    ): WebhookEvent {
        // Extract payload from request
        $payload = $request->input();

        // Store webhook in repository
        return $this->repository->storeIncomingEvent(
            $source,
            $event,
            $payload,
            $request,
            [
                'is_valid' => $isValid,
                'validation_message' => $validationMessage,
            ]
        );
    }

    /**
     * Run before hooks for a source.
     *
     * @throws WebhookProcessingException
     */
    private function runBeforeHooks(string $source, Request $request): void
    {
        if (! isset($this->beforeHooks[$source])) {
            return;
        }

        foreach ($this->beforeHooks[$source] as $hook) {
            try {
                $result = $hook($request);

                // If hook returns false, stop processing
                if ($result === false) {
                    throw new WebhookProcessingException(
                        $source,
                        'unknown',
                        null,
                        null,
                        'Webhook processing stopped by before hook'
                    );
                }
            } catch (\Throwable $e) {
                if ($e instanceof WebhookProcessingException) {
                    throw $e;
                }

                throw new WebhookProcessingException(
                    $source,
                    'unknown',
                    null,
                    null,
                    'Error in before hook: ' . $e->getMessage(),
                    0,
                    $e
                );
            }
        }
    }

    /**
     * Run after hooks for a source.
     */
    private function runAfterHooks(string $source, WebhookEvent $webhookEvent, mixed $result): void
    {
        if (! isset($this->afterHooks[$source])) {
            return;
        }

        foreach ($this->afterHooks[$source] as $hook) {
            try {
                $hook($webhookEvent, $result);
            } catch (\Throwable $e) {
                // Log but continue with other hooks
                Log::warning("Error in after hook for {$source}", [
                    'error' => $e->getMessage(),
                    'webhook_id' => $webhookEvent->uuid,
                ]);
            }
        }
    }

    /**
     * Get the secret for a specific source.
     */
    private function getSecretForSource(string $source): ?string
    {
        // Check provider-specific config first
        $secret = config("webhook-owlery.providers.{$source}.secret_key");

        if ($secret) {
            return $secret;
        }

        // Fall back to default secret
        return config('webhook-owlery.security.default_signature_key');
    }

    /**
     * Get the validator for a specific source.
     */
    private function getValidatorForSource(string $source): SignatureValidatorContract
    {
        // Check if there's a specific validator configured for this source
        $validatorType = config("webhook-owlery.providers.{$source}.signature_validator");

        if ($validatorType) {
            // If it's a class name, instantiate it
            if (class_exists($validatorType)) {
                return new $validatorType;
            }

            // If it's a registered validator type
            $validators = config('webhook-owlery.receiving.validators', []);
            if (isset($validators[$validatorType]) && class_exists($validators[$validatorType])) {
                return new $validators[$validatorType];
            }

            // If it's a known provider, use the provider-specific validator
            if (in_array($source, ['stripe', 'github', 'shopify', 'paypal', 'slack'])) {
                return new ProviderSpecificValidator($source);
            }
        }

        // Default to the injected validator
        return $this->validator;
    }

    /**
     * Get options for a specific source.
     */
    private function getOptionsForSource(string $source): array
    {
        // Get provider-specific config
        $providerConfig = config("webhook-owlery.providers.{$source}", []);

        // For provider-specific options, only keep relevant keys
        $relevantKeys = [
            'signature_header',
            'signature_format',
            'timestamp_header',
            'algorithm',
            'prefix',
        ];

        return Arr::only($providerConfig, $relevantKeys);
    }
}
