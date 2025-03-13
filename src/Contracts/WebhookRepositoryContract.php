<?php

namespace WizardingCode\WebhookOwlery\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use WizardingCode\WebhookOwlery\Models\WebhookDelivery;
use WizardingCode\WebhookOwlery\Models\WebhookEndpoint;
use WizardingCode\WebhookOwlery\Models\WebhookEvent;
use WizardingCode\WebhookOwlery\Models\WebhookSubscription;

interface WebhookRepositoryContract
{
    /**
     * Store a received webhook event.
     *
     * @param string  $source   The source service (stripe, github, etc)
     * @param string  $event    The event name or type
     * @param array   $payload  The webhook payload
     * @param Request $request  The original request
     * @param array   $metadata Additional metadata
     */
    public function storeIncomingEvent(string $source, string $event, array $payload, Request $request, array $metadata = []): WebhookEvent;

    /**
     * Store an outgoing webhook delivery.
     *
     * @param string               $event       The event type
     * @param string               $destination The destination URL
     * @param array                $payload     The webhook payload
     * @param array                $options     Additional options
     * @param WebhookEndpoint|null $endpoint    The associated endpoint if any
     */
    public function storeOutgoingDelivery(string $event, string $destination, array $payload, array $options = [], ?WebhookEndpoint $endpoint = null): WebhookDelivery;

    /**
     * Update the status of a webhook delivery.
     *
     * @param WebhookDelivery|int|string $delivery The delivery model, ID, or UUID
     * @param string                     $status   The new status
     * @param array                      $metadata Additional metadata
     */
    public function updateDeliveryStatus(WebhookDelivery|int|string $delivery, string $status, array $metadata = []): WebhookDelivery;

    /**
     * Mark a delivery as succeeded.
     *
     * @param WebhookDelivery|int|string $delivery        The delivery model, ID, or UUID
     * @param int                        $statusCode      HTTP status code
     * @param string|null                $responseBody    Response body
     * @param array|null                 $responseHeaders Response headers
     * @param int|null                   $responseTime    Response time in ms
     */
    public function markDeliverySucceeded(WebhookDelivery|int|string $delivery, int $statusCode, ?string $responseBody = null, ?array $responseHeaders = null, ?int $responseTime = null): WebhookDelivery;

    /**
     * Mark a delivery as failed.
     *
     * @param WebhookDelivery|int|string $delivery        The delivery model, ID, or UUID
     * @param int|null                   $statusCode      HTTP status code if any
     * @param string|null                $responseBody    Response body if any
     * @param array|null                 $responseHeaders Response headers if any
     * @param string|null                $errorMessage    Error message
     * @param string|null                $errorDetail     Detailed error information
     * @param int|null                   $responseTime    Response time in ms
     */
    public function markDeliveryFailed(WebhookDelivery|int|string $delivery, ?int $statusCode = null, ?string $responseBody = null, ?array $responseHeaders = null, ?string $errorMessage = null, ?string $errorDetail = null, ?int $responseTime = null): WebhookDelivery;

    /**
     * Find webhook deliveries by various criteria.
     *
     * @param array $criteria Search criteria
     * @param int   $perPage  Items per page (0 for all)
     */
    public function findDeliveries(array $criteria, int $perPage = 15): Collection|LengthAwarePaginator;

    /**
     * Find webhook events by various criteria.
     *
     * @param array $criteria Search criteria
     * @param int   $perPage  Items per page (0 for all)
     */
    public function findEvents(array $criteria, int $perPage = 15): Collection|LengthAwarePaginator;

    /**
     * Find webhook endpoints by various criteria.
     *
     * @param array $criteria Search criteria
     * @param int   $perPage  Items per page (0 for all)
     */
    public function findEndpoints(array $criteria, int $perPage = 15): Collection|LengthAwarePaginator;

    /**
     * Find webhook subscriptions by various criteria.
     *
     * @param array $criteria Search criteria
     * @param int   $perPage  Items per page (0 for all)
     */
    public function findSubscriptions(array $criteria, int $perPage = 15): Collection|LengthAwarePaginator;

    /**
     * Find endpoints for a specific event type.
     *
     * @param string $eventType  Event type or pattern
     * @param bool   $activeOnly Only include active endpoints
     */
    public function findEndpointsForEvent(string $eventType, bool $activeOnly = true): Collection;

    /**
     * Find subscriptions for a specific event type.
     *
     * @param string $eventType  Event type
     * @param array  $eventData  Event data for filtering
     * @param bool   $activeOnly Only include active subscriptions
     */
    public function findSubscriptionsForEvent(string $eventType, array $eventData = [], bool $activeOnly = true): Collection;

    /**
     * Get a webhook delivery by ID or UUID.
     *
     * @param int|string $id ID or UUID
     */
    public function getDelivery(int|string $id): ?WebhookDelivery;

    /**
     * Get a webhook event by ID or UUID.
     *
     * @param int|string $id ID or UUID
     */
    public function getEvent(int|string $id): ?WebhookEvent;

    /**
     * Get a webhook endpoint by ID or UUID.
     *
     * @param int|string $id ID or UUID
     */
    public function getEndpoint(int|string $id): ?WebhookEndpoint;

    /**
     * Get a webhook subscription by ID or UUID.
     *
     * @param int|string $id ID or UUID
     */
    public function getSubscription(int|string $id): ?WebhookSubscription;

    /**
     * Create a new webhook endpoint.
     *
     * @param array $data Endpoint data
     */
    public function createEndpoint(array $data): WebhookEndpoint;

    /**
     * Create a new webhook subscription.
     *
     * @param WebhookEndpoint|int|string|array $endpoint  The endpoint model, ID, UUID, or array of data
     * @param string|null                      $eventType Event type or pattern
     * @param array                            $filters   Event filters
     * @param array                            $options   Additional options
     */
    public function createSubscription(WebhookEndpoint|int|string|array $endpoint, ?string $eventType = null, array $filters = [], array $options = []): WebhookSubscription;

    /**
     * Update a webhook endpoint.
     *
     * @param WebhookEndpoint|int|string $endpoint The endpoint model, ID, or UUID
     * @param array                      $data     Data to update
     */
    public function updateEndpoint(WebhookEndpoint|int|string $endpoint, array $data): WebhookEndpoint;

    /**
     * Update a webhook subscription.
     *
     * @param WebhookSubscription|int|string $subscription The subscription model, ID, or UUID
     * @param array                          $data         Data to update
     */
    public function updateSubscription(WebhookSubscription|int|string $subscription, array $data): WebhookSubscription;

    /**
     * Delete a webhook endpoint.
     *
     * @param WebhookEndpoint|int|string $endpoint The endpoint model, ID, or UUID
     * @param bool                       $force    Force delete (true) or soft delete (false)
     */
    public function deleteEndpoint(WebhookEndpoint|int|string $endpoint, bool $force = false): bool;

    /**
     * Delete a webhook subscription.
     *
     * @param WebhookSubscription|int|string $subscription The subscription model, ID, or UUID
     * @param bool                           $force        Force delete (true) or soft delete (false)
     */
    public function deleteSubscription(WebhookSubscription|int|string $subscription, bool $force = false): bool;

    /**
     * Clean up old webhook data according to retention policy.
     *
     * @param int|null $daysToKeep Number of days to keep data (null = use config)
     *
     * @return array Stats about cleaned up records
     */
    public function cleanupOldData(?int $daysToKeep = null): array;

    /**
     * Record a webhook event.
     *
     * @param string $type    Event type
     * @param array  $payload Event payload
     * @param array  $options Additional options
     */
    public function recordEvent(string $type, array $payload, array $options = []): WebhookEvent;

    /**
     * Record a webhook delivery.
     *
     * @param array $data Delivery data
     */
    public function recordDelivery(array $data): WebhookDelivery;

    /**
     * Get active subscriptions for a specific event type.
     *
     * @param string $eventType Event type
     */
    public function getActiveSubscriptionsByEventType(string $eventType): Collection;

    /**
     * Get the last delivery in the system.
     */
    public function getLastDelivery(): ?WebhookDelivery;
}
