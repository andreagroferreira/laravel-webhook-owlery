<?php

namespace WizardingCode\WebhookOwlery\Contracts;

use Illuminate\Http\Request;
use WizardingCode\WebhookOwlery\Models\WebhookDelivery;
use WizardingCode\WebhookOwlery\Models\WebhookEndpoint;
use WizardingCode\WebhookOwlery\Models\WebhookEvent;
use WizardingCode\WebhookOwlery\Models\WebhookSubscription;

interface OwleryContract
{
    /**
     * Get the webhook receiver.
     */
    public function receiver(): WebhookReceiverContract;

    /**
     * Get the webhook dispatcher.
     */
    public function dispatcher(): WebhookDispatcherContract;

    /**
     * Get the webhook repository.
     */
    public function repository(): WebhookRepositoryContract;

    /**
     * Get the webhook subscription manager.
     */
    public function subscriptions(): WebhookSubscriptionContract;

    /**
     * Get the circuit breaker.
     */
    public function circuitBreaker(): CircuitBreakerContract;

    /**
     * Create a webhook endpoint.
     *
     * @param string $name    Name of the endpoint
     * @param string $url     URL of the endpoint
     * @param array  $events  Events the endpoint will receive
     * @param array  $options Additional options
     */
    public function createEndpoint(string $name, string $url, array $events = [], array $options = []): WebhookEndpoint;

    /**
     * Create a webhook subscription.
     *
     * @param WebhookEndpoint|int|string $endpoint  The endpoint to subscribe
     * @param string                     $eventType Event type or pattern
     * @param array                      $filters   Event filters
     * @param array                      $options   Additional options
     */
    public function subscribe(WebhookEndpoint|int|string $endpoint, string $eventType, array $filters = [], array $options = []): WebhookSubscription;

    /**
     * Handle an incoming webhook request.
     *
     * @param string  $source  Source of the webhook
     * @param Request $request The HTTP request
     */
    public function handleIncoming(string $source, Request $request): WebhookEvent;

    /**
     * Send a webhook to a destination URL.
     *
     * @param string $url     The destination URL
     * @param string $event   The event type
     * @param array  $payload The webhook payload
     * @param array  $options Dispatch options
     */
    public function sendWebhook(string $url, string $event, array $payload, array $options = []): WebhookDelivery;

    /**
     * Broadcast an event to all subscribed endpoints.
     *
     * @param string $event   The event type
     * @param array  $payload The webhook payload
     * @param array  $options Dispatch options
     *
     * @return array Array of WebhookDelivery instances
     */
    public function broadcastEvent(string $event, array $payload, array $options = []): array;

    /**
     * Register a handler for a specific webhook event.
     *
     * @param string   $source  The source service
     * @param string   $event   The event name or pattern
     * @param callable $handler The handler function
     */
    public function on(string $source, string $event, callable $handler): self;

    /**
     * Get signature validator for a specific type.
     *
     * @param string $type The validator type
     */
    public function getSignatureValidator(string $type = 'hmac'): SignatureValidatorContract;

    /**
     * Clean up old webhook data according to retention policy.
     *
     * @param int|null $daysToKeep Number of days to keep data (null = use config)
     *
     * @return array Stats about cleaned up records
     */
    public function cleanup(?int $daysToKeep = null): array;

    /**
     * Get stats about webhook activity.
     *
     * @param int $days Number of days to include
     */
    public function stats(int $days = 30): array;

    /**
     * Get metrics about webhook success rates and performance.
     *
     * @param int $days Number of days to include
     */
    public function metrics(int $days = 30): array;

    /**
     * Retry failed webhook deliveries.
     *
     * @param array $criteria Criteria to select deliveries to retry
     *
     * @return int Number of deliveries queued for retry
     */
    public function retryFailed(array $criteria = []): int;
}
