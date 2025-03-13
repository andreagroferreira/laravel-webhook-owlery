<?php

namespace WizardingCode\WebhookOwlery\Repositories;

use DateTime;
use Illuminate\Support\Collection;
use WizardingCode\WebhookOwlery\Contracts\WebhookSubscriptionContract;
use WizardingCode\WebhookOwlery\Exceptions\EndpointException;
use WizardingCode\WebhookOwlery\Models\WebhookEndpoint;
use WizardingCode\WebhookOwlery\Models\WebhookSubscription;

class EloquentWebhookSubscriptionRepository implements WebhookSubscriptionContract
{
    /**
     * Create a new webhook subscription.
     *
     * @param WebhookEndpoint|int|string $endpoint  The endpoint to subscribe
     * @param string                     $eventType Event type or pattern
     * @param array                      $filters   Event filters
     * @param array                      $options   Additional options
     *
     * @throws EndpointException
     */
    final public function create(WebhookEndpoint|int|string $endpoint, string $eventType, array $filters = [], array $options = []): WebhookSubscription
    {
        $endpoint = $this->resolveEndpoint($endpoint);

        if (! $endpoint) {
            throw new EndpointException('Endpoint not found');
        }

        $data = [
            'webhook_endpoint_id' => $endpoint->id,
            'event_type' => $eventType,
            'event_filters' => ! empty($filters) ? $filters : null,
            'is_active' => $options['is_active'] ?? true,
            'expires_at' => $options['expires_at'] ?? null,
            'max_deliveries' => $options['max_deliveries'] ?? null,
            'description' => $options['description'] ?? null,
            'metadata' => $options['metadata'] ?? null,
            'created_by' => $options['created_by'] ?? null,
        ];

        return WebhookSubscription::create($data);
    }

    /**
     * Update an existing webhook subscription.
     *
     * @param WebhookSubscription|int|string $subscription The subscription model, ID, or UUID
     * @param array                          $data         The data to update
     *
     * @throws EndpointException
     */
    final public function update(WebhookSubscription|int|string $subscription, array $data): WebhookSubscription
    {
        $subscription = $this->resolveSubscription($subscription);

        if (! $subscription) {
            throw new EndpointException('Subscription not found');
        }

        // Record who updated it
        if (isset($data['updated_by'])) {
            $subscription->updated_by = $data['updated_by'];
        }

        $subscription->fill($data);
        $subscription->save();

        return $subscription;
    }

    /**
     * Delete a webhook subscription.
     *
     * @param WebhookSubscription|int|string $subscription The subscription model, ID, or UUID
     * @param bool                           $force        Force delete (true) or soft delete (false)
     *
     * @throws EndpointException
     */
    final public function delete(WebhookSubscription|int|string $subscription, bool $force = false): bool
    {
        $subscription = $this->resolveSubscription($subscription);

        if (! $subscription) {
            throw new EndpointException('Subscription not found');
        }

        if ($force) {
            return (bool) $subscription->forceDelete();
        }

        return (bool) $subscription->delete();
    }

    /**
     * Activate a subscription.
     *
     * @param WebhookSubscription|int|string $subscription The subscription model, ID, or UUID
     *
     * @throws EndpointException
     */
    final public function activate(WebhookSubscription|int|string $subscription): WebhookSubscription
    {
        $subscription = $this->resolveSubscription($subscription);

        if (! $subscription) {
            throw new EndpointException('Subscription not found');
        }

        return $subscription->activate();
    }

    /**
     * Deactivate a subscription.
     *
     * @param WebhookSubscription|int|string $subscription The subscription model, ID, or UUID
     *
     * @throws EndpointException
     */
    final public function deactivate(WebhookSubscription|int|string $subscription): WebhookSubscription
    {
        $subscription = $this->resolveSubscription($subscription);

        if (! $subscription) {
            throw new EndpointException('Subscription not found');
        }

        return $subscription->deactivate();
    }

    /**
     * Set an expiration date for a subscription.
     *
     * @param WebhookSubscription|int|string $subscription The subscription model, ID, or UUID
     * @param DateTime                       $expiresAt    Expiration date
     *
     * @throws EndpointException
     */
    final public function setExpiration(WebhookSubscription|int|string $subscription, DateTime $expiresAt): WebhookSubscription
    {
        $subscription = $this->resolveSubscription($subscription);

        if (! $subscription) {
            throw new EndpointException('Subscription not found');
        }

        return $subscription->expiresAt($expiresAt);
    }

    /**
     * Set a delivery limit for a subscription.
     *
     * @param WebhookSubscription|int|string $subscription The subscription model, ID, or UUID
     * @param int                            $limit        Maximum number of deliveries
     *
     * @throws EndpointException
     */
    final public function setDeliveryLimit(WebhookSubscription|int|string $subscription, int $limit): WebhookSubscription
    {
        $subscription = $this->resolveSubscription($subscription);

        if (! $subscription) {
            throw new EndpointException('Subscription not found');
        }

        $subscription->max_deliveries = $limit;
        $subscription->save();

        return $subscription;
    }

    /**
     * Find subscriptions matching criteria.
     *
     * @param array $criteria Search criteria
     */
    final public function find(array $criteria): Collection
    {
        $query = WebhookSubscription::query();

        // Apply filters
        if (isset($criteria['endpoint_id'])) {
            $query->where('webhook_endpoint_id', $criteria['endpoint_id']);
        }

        if (isset($criteria['event_type'])) {
            $query->where('event_type', $criteria['event_type']);
        }

        if (isset($criteria['is_active']) !== null) {
            $query->where('is_active', (bool) $criteria['is_active']);
        }

        if (isset($criteria['created_by'])) {
            $query->where('created_by', $criteria['created_by']);
        }

        if (isset($criteria['expires_at'])) {
            if ($criteria['expires_at'] === 'not_expired') {
                $query->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                });
            } elseif ($criteria['expires_at'] === 'expired') {
                $query->where('expires_at', '<=', now());
            }
        }

        // Add ordering
        $query->orderBy($criteria['order_by'] ?? 'created_at', $criteria['order'] ?? 'desc');

        return $query->get();
    }

    /**
     * Find subscriptions for a specific event.
     *
     * @param string $eventType  Event type
     * @param array  $eventData  Event data for filtering
     * @param bool   $activeOnly Only include active subscriptions
     */
    final public function findForEvent(string $eventType, array $eventData = [], bool $activeOnly = true): Collection
    {
        $query = WebhookSubscription::query()
            ->with('endpoint')
            ->whereHas('endpoint', function ($q) use ($activeOnly) {
                if ($activeOnly) {
                    $q->where('is_active', true);
                }
            });

        if ($activeOnly) {
            $query->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->where(function ($q) {
                    $q->whereNull('max_deliveries')
                        ->orWhereRaw('delivery_count < max_deliveries');
                });
        }

        // Match event type
        $query->where(function ($q) use ($eventType) {
            // Exact match
            $q->where('event_type', $eventType)
                // Wildcard match
                ->orWhere(function ($subQuery) use ($eventType) {
                    $subQuery->where('event_type', 'like', '%*%')
                        ->whereRaw('? LIKE REPLACE(event_type, "*", "%")', [$eventType]);
                });
        });

        // Get all subscriptions
        $subscriptions = $query->get();

        // Filter by event data if provided
        if (! empty($eventData)) {
            $subscriptions = $subscriptions->filter(function ($subscription) use ($eventData) {
                $filters = $subscription->event_filters ?? [];

                // No filters means all events match
                if (empty($filters)) {
                    return true;
                }

                // Check each filter
                foreach ($filters as $key => $value) {
                    // Skip if key doesn't exist in event data
                    if (! array_key_exists($key, $eventData)) {
                        return false;
                    }

                    // Check for equality
                    if (is_scalar($value) && $eventData[$key] != $value) {
                        return false;
                    }

                    // Check for array contains
                    if (is_array($value) && (! is_array($eventData[$key]) || ! in_array($eventData[$key], $value))) {
                        return false;
                    }
                }

                return true;
            });
        }

        return $subscriptions;
    }

    /**
     * Get a subscription by ID or UUID.
     *
     * @param int|string $id ID or UUID
     */
    final public function get(int|string $id): ?WebhookSubscription
    {
        return $this->resolveSubscription($id);
    }

    /**
     * Get all subscriptions for an endpoint.
     *
     * @param WebhookEndpoint|int|string $endpoint   The endpoint model, ID, or UUID
     * @param bool                       $activeOnly Only include active subscriptions
     *
     * @throws EndpointException
     */
    final public function getForEndpoint(WebhookEndpoint|int|string $endpoint, bool $activeOnly = false): Collection
    {
        $endpoint = $this->resolveEndpoint($endpoint);

        if (! $endpoint) {
            throw new EndpointException('Endpoint not found');
        }

        $query = $endpoint->subscriptions();

        if ($activeOnly) {
            $query->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                });
        }

        return $query->get();
    }

    /**
     * Resolve a webhook subscription from an ID, UUID, or model.
     */
    private function resolveSubscription(WebhookSubscription|int|string $subscription): ?WebhookSubscription
    {
        if ($subscription instanceof WebhookSubscription) {
            return $subscription;
        }

        if (is_string($subscription) && ! is_numeric($subscription)) {
            return WebhookSubscription::where('uuid', $subscription)->first();
        }

        return WebhookSubscription::find($subscription);
    }

    /**
     * Resolve a webhook endpoint from an ID, UUID, or model.
     */
    private function resolveEndpoint(WebhookEndpoint|int|string $endpoint): ?WebhookEndpoint
    {
        if ($endpoint instanceof WebhookEndpoint) {
            return $endpoint;
        }

        if (is_string($endpoint) && ! is_numeric($endpoint)) {
            return WebhookEndpoint::where('uuid', $endpoint)->first();
        }

        return WebhookEndpoint::find($endpoint);
    }
}
