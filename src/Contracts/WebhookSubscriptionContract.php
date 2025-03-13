<?php

namespace WizardingCode\WebhookOwlery\Contracts;

use Illuminate\Support\Collection;
use WizardingCode\WebhookOwlery\Models\WebhookEndpoint;
use WizardingCode\WebhookOwlery\Models\WebhookSubscription;

interface WebhookSubscriptionContract
{
    /**
     * Create a new webhook subscription.
     *
     * @param WebhookEndpoint|int|string $endpoint  The endpoint to subscribe
     * @param string                     $eventType Event type or pattern
     * @param array                      $filters   Event filters
     * @param array                      $options   Additional options
     */
    public function create(WebhookEndpoint|int|string $endpoint, string $eventType, array $filters = [], array $options = []): WebhookSubscription;

    /**
     * Update an existing webhook subscription.
     *
     * @param WebhookSubscription|int|string $subscription The subscription model, ID, or UUID
     * @param array                          $data         The data to update
     */
    public function update(WebhookSubscription|int|string $subscription, array $data): WebhookSubscription;

    /**
     * Delete a webhook subscription.
     *
     * @param WebhookSubscription|int|string $subscription The subscription model, ID, or UUID
     * @param bool                           $force        Force delete (true) or soft delete (false)
     */
    public function delete(WebhookSubscription|int|string $subscription, bool $force = false): bool;

    /**
     * Activate a subscription.
     *
     * @param WebhookSubscription|int|string $subscription The subscription model, ID, or UUID
     */
    public function activate(WebhookSubscription|int|string $subscription): WebhookSubscription;

    /**
     * Deactivate a subscription.
     *
     * @param WebhookSubscription|int|string $subscription The subscription model, ID, or UUID
     */
    public function deactivate(WebhookSubscription|int|string $subscription): WebhookSubscription;

    /**
     * Set an expiration date for a subscription.
     *
     * @param WebhookSubscription|int|string $subscription The subscription model, ID, or UUID
     * @param \DateTime                      $expiresAt    Expiration date
     */
    public function setExpiration(WebhookSubscription|int|string $subscription, \DateTime $expiresAt): WebhookSubscription;

    /**
     * Set a delivery limit for a subscription.
     *
     * @param WebhookSubscription|int|string $subscription The subscription model, ID, or UUID
     * @param int                            $limit        Maximum number of deliveries
     */
    public function setDeliveryLimit(WebhookSubscription|int|string $subscription, int $limit): WebhookSubscription;

    /**
     * Find subscriptions matching criteria.
     *
     * @param array $criteria Search criteria
     */
    public function find(array $criteria): Collection;

    /**
     * Find subscriptions for a specific event.
     *
     * @param string $eventType  Event type
     * @param array  $eventData  Event data for filtering
     * @param bool   $activeOnly Only include active subscriptions
     */
    public function findForEvent(string $eventType, array $eventData = [], bool $activeOnly = true): Collection;

    /**
     * Get a subscription by ID or UUID.
     *
     * @param int|string $id ID or UUID
     */
    public function get(int|string $id): ?WebhookSubscription;

    /**
     * Get all subscriptions for an endpoint.
     *
     * @param WebhookEndpoint|int|string $endpoint   The endpoint model, ID, or UUID
     * @param bool                       $activeOnly Only include active subscriptions
     */
    public function getForEndpoint(WebhookEndpoint|int|string $endpoint, bool $activeOnly = false): Collection;
}
