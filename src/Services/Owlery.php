<?php

namespace WizardingCode\WebhookOwlery\Services;

use Illuminate\Http\Request;
use WizardingCode\WebhookOwlery\Contracts\CircuitBreakerContract;
use WizardingCode\WebhookOwlery\Contracts\OwleryContract;
use WizardingCode\WebhookOwlery\Contracts\SignatureValidatorContract;
use WizardingCode\WebhookOwlery\Contracts\WebhookDispatcherContract;
use WizardingCode\WebhookOwlery\Contracts\WebhookReceiverContract;
use WizardingCode\WebhookOwlery\Contracts\WebhookRepositoryContract;
use WizardingCode\WebhookOwlery\Contracts\WebhookSubscriptionContract;
use WizardingCode\WebhookOwlery\Models\WebhookDelivery;
use WizardingCode\WebhookOwlery\Models\WebhookEndpoint;
use WizardingCode\WebhookOwlery\Models\WebhookEvent;
use WizardingCode\WebhookOwlery\Models\WebhookSubscription;
use WizardingCode\WebhookOwlery\Validators\HmacSignatureValidator;
use WizardingCode\WebhookOwlery\Validators\ProviderSpecificValidator;

class Owlery implements OwleryContract
{
    /**
     * The webhook receiver implementation.
     */
    private WebhookReceiverContract $receiver;

    /**
     * The webhook dispatcher implementation.
     */
    private WebhookDispatcherContract $dispatcher;

    /**
     * The webhook repository implementation.
     */
    private WebhookRepositoryContract $repository;

    /**
     * The webhook subscription implementation.
     */
    private ?WebhookSubscriptionContract $subscriptions = null;

    /**
     * The circuit breaker implementation.
     */
    private ?CircuitBreakerContract $circuitBreaker;

    /**
     * Subscriptions to target for the next webhook dispatch.
     */
    private ?array $targetSubscriptions = null;

    /**
     * Metadata to include in the next webhook dispatch.
     */
    private array $metadata = [];

    /**
     * Create a new Owlery instance.
     *
     * @return void
     */
    public function __construct(
        WebhookRepositoryContract $repository,
        WebhookReceiverContract $receiver,
        WebhookDispatcherContract $dispatcher,
        ?WebhookSubscriptionContract $subscriptions = null,
        ?CircuitBreakerContract $circuitBreaker = null
    ) {
        $this->repository = $repository;
        $this->receiver = $receiver;
        $this->dispatcher = $dispatcher;
        $this->subscriptions = $subscriptions;
        $this->circuitBreaker = $circuitBreaker;
    }

    /**
     * Get the webhook receiver.
     */
    final public function receiver(): WebhookReceiverContract
    {
        return $this->receiver;
    }

    /**
     * Get the webhook dispatcher.
     */
    final public function dispatcher(): WebhookDispatcherContract
    {
        return $this->dispatcher;
    }

    /**
     * Get the webhook repository.
     */
    final public function repository(): WebhookRepositoryContract
    {
        return $this->repository;
    }

    /**
     * Get the webhook subscription manager.
     */
    final public function subscriptions(): WebhookSubscriptionContract
    {
        if (! $this->subscriptions) {
            throw new \RuntimeException('WebhookSubscriptionContract implementation not provided');
        }

        return $this->subscriptions;
    }

    /**
     * Set specific subscriptions to target for the next webhook.
     *
     * @param array $subscriptionIds Subscription IDs
     *
     * @return $this
     */
    final public function to(array $subscriptionIds): self
    {
        $this->targetSubscriptions = $subscriptionIds;

        return $this;
    }

    /**
     * Add metadata to the next webhook dispatch.
     *
     * @param array $metadata Metadata to include
     *
     * @return $this
     */
    final public function withMetadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Get the circuit breaker.
     */
    final public function circuitBreaker(): CircuitBreakerContract
    {
        if (! $this->circuitBreaker) {
            throw new \RuntimeException('CircuitBreakerContract implementation not provided');
        }

        return $this->circuitBreaker;
    }

    /**
     * Create a webhook endpoint.
     *
     * @param string $name    Name of the endpoint
     * @param string $url     URL of the endpoint
     * @param array  $events  Events the endpoint will receive
     * @param array  $options Additional options
     */
    final public function createEndpoint(string $name, string $url, array $events = [], array $options = []): WebhookEndpoint
    {
        $data = array_merge([
            'name' => $name,
            'url' => $url,
            'events' => $events,
            'is_active' => true,
        ], $options);

        return $this->repository->createEndpoint($data);
    }

    /**
     * Create a webhook subscription.
     *
     * @param WebhookEndpoint|int|string $endpoint  The endpoint to subscribe
     * @param string                     $eventType Event type or pattern
     * @param array                      $filters   Event filters
     * @param array                      $options   Additional options
     */
    final public function subscribe(WebhookEndpoint|int|string $endpoint, string $eventType, array $filters = [], array $options = []): WebhookSubscription
    {
        return $this->subscriptions()->create($endpoint, $eventType, $filters, $options);
    }

    /**
     * Handle an incoming webhook request.
     *
     * @param string  $source  Source of the webhook
     * @param Request $request The HTTP request
     */
    final public function handleIncoming(string $source, Request $request): WebhookEvent
    {
        // This will either return a response or throw an exception
        $result = $this->receiver->handleRequest($source, $request);

        // If the receiver processed the webhook synchronously, it may have returned a WebhookEvent
        if ($result instanceof WebhookEvent) {
            return $result;
        }

        // Otherwise, extract the webhook ID from the response
        $webhookId = null;

        if (method_exists($result, 'getData')) {
            $data = $result->getData(true);
            $webhookId = $data['webhook_id'] ?? null;
        }

        // Return the webhook event model if we can find it
        if ($webhookId) {
            $webhookEvent = $this->repository->getEvent($webhookId);

            if ($webhookEvent) {
                return $webhookEvent;
            }
        }

        // Create a fallback event model if we couldn't extract the real one
        return new WebhookEvent([
            'source' => $source,
            'event' => 'unknown',
            'payload' => $request->input(),
            'is_processed' => true,
            'processing_status' => 'queued',
        ]);
    }

    /**
     * Send a webhook to a destination URL.
     *
     * @param string $url     The destination URL
     * @param string $event   The event type
     * @param array  $payload The webhook payload
     * @param array  $options Dispatch options
     */
    final public function sendWebhook(string $url, string $event, array $payload, array $options = []): WebhookDelivery
    {
        return $this->dispatcher->send($url, $event, $payload, $options);
    }

    /**
     * Broadcast an event to all subscribed endpoints.
     *
     * @param string $event   The event type
     * @param array  $payload The webhook payload
     * @param array  $options Dispatch options
     *
     * @return array Array of WebhookDelivery instances
     */
    final public function broadcastEvent(string $event, array $payload, array $options = []): array
    {
        return $this->dispatcher->broadcast($event, $payload, $options);
    }

    /**
     * Register a handler for a specific webhook event.
     *
     * @param string   $source  The source service
     * @param string   $event   The event name or pattern
     * @param callable $handler The handler function
     */
    final public function on(string $source, string $event, callable $handler): self
    {
        $this->receiver->on($source, $event, $handler);

        return $this;
    }

    /**
     * Get signature validator for a specific type.
     *
     * @param string $type The validator type
     */
    final public function getSignatureValidator(string $type = 'hmac'): SignatureValidatorContract
    {
        $validators = config('webhook-owlery.receiving.validators', []);

        // If the type is a known service, use the provider-specific validator
        if (in_array($type, ['stripe', 'github', 'shopify', 'paypal', 'slack'])) {
            return new ProviderSpecificValidator($type);
        }

        // If the type is a registered validator type
        if (isset($validators[$type])) {
            $validatorClass = $validators[$type];

            return new $validatorClass;
        }

        // Default to HMAC
        return new HmacSignatureValidator;
    }

    /**
     * Clean up old webhook data according to retention policy.
     *
     * @param int|null $daysToKeep Number of days to keep data (null = use config)
     *
     * @return array Stats about cleaned up records
     */
    final public function cleanup(?int $daysToKeep = null): array
    {
        return $this->repository->cleanupOldData($daysToKeep);
    }

    /**
     * Send a webhook to all subscribed endpoints.
     *
     * @param string $event   Event type
     * @param array  $payload Webhook payload
     * @param array  $options Additional options
     *
     * @return array Array of webhook deliveries
     */
    final public function send(string $event, array $payload, array $options = []): array
    {
        // Merge in any metadata
        if (! empty($this->metadata)) {
            $options['metadata'] = array_merge($options['metadata'] ?? [], $this->metadata);

            // Reset metadata after use
            $this->metadata = [];
        }

        // Handle specific subscription targeting
        if ($this->targetSubscriptions !== null) {
            $subscriptions = $this->targetSubscriptions;

            // Reset target subscriptions after use
            $this->targetSubscriptions = null;

            return $this->dispatcher->broadcast($event, $payload, array_merge($options, [
                'subscriptions' => $subscriptions,
            ]));
        }

        // Normal broadcast to all subscribers
        return $this->dispatcher->broadcast($event, $payload, $options);
    }

    /**
     * Get stats about webhook activity.
     *
     * @param int $days Number of days to include
     */
    final public function stats(int $days = 30): array
    {
        $startDate = now()->subDays($days)->startOfDay();

        // Get counts from repository
        $incomingEvents = $this->repository->findEvents([
            'start_date' => $startDate,
        ], 0);

        $outgoingDeliveries = $this->repository->findDeliveries([
            'start_date' => $startDate,
        ], 0);

        // Calculate stats
        $incomingCount = $incomingEvents->count();
        $incomingValidCount = $incomingEvents->where('is_valid', true)->count();
        $incomingInvalidCount = $incomingEvents->where('is_valid', false)->count();
        $incomingProcessedCount = $incomingEvents->where('is_processed', true)->count();
        $incomingUnprocessedCount = $incomingEvents->where('is_processed', false)->count();
        $incomingSuccessCount = $incomingEvents->where('processing_status', 'success')->count();
        $incomingErrorCount = $incomingEvents->where('processing_status', 'error')->count();

        $outgoingCount = $outgoingDeliveries->count();
        $outgoingSuccessCount = $outgoingDeliveries->where('status', WebhookDelivery::STATUS_SUCCESS)->count();
        $outgoingFailedCount = $outgoingDeliveries->where('status', WebhookDelivery::STATUS_FAILED)->count();
        $outgoingPendingCount = $outgoingDeliveries->where('status', WebhookDelivery::STATUS_PENDING)->count();
        $outgoingRetryingCount = $outgoingDeliveries->where('status', WebhookDelivery::STATUS_RETRYING)->count();

        // Group by sources and events
        $incomingBySource = $incomingEvents->groupBy('source')
            ->map(fn ($group) => $group->count())
            ->toArray();

        $incomingByEvent = $incomingEvents->groupBy('event')
            ->map(fn ($group) => $group->count())
            ->toArray();

        $outgoingByEvent = $outgoingDeliveries->groupBy('event')
            ->map(fn ($group) => $group->count())
            ->toArray();

        // Return stats
        return [
            'period' => [
                'days' => $days,
                'start_date' => $startDate->toDateTimeString(),
                'end_date' => now()->toDateTimeString(),
            ],
            'incoming' => [
                'total' => $incomingCount,
                'valid' => $incomingValidCount,
                'invalid' => $incomingInvalidCount,
                'processed' => $incomingProcessedCount,
                'unprocessed' => $incomingUnprocessedCount,
                'success' => $incomingSuccessCount,
                'error' => $incomingErrorCount,
                'by_source' => $incomingBySource,
                'by_event' => $incomingByEvent,
            ],
            'outgoing' => [
                'total' => $outgoingCount,
                'success' => $outgoingSuccessCount,
                'failed' => $outgoingFailedCount,
                'pending' => $outgoingPendingCount,
                'retrying' => $outgoingRetryingCount,
                'by_event' => $outgoingByEvent,
            ],
        ];
    }

    /**
     * Get metrics about webhook success rates and performance.
     *
     * @param int $days Number of days to include
     */
    final public function metrics(int $days = 30): array
    {
        $startDate = now()->subDays($days)->startOfDay();

        // Get deliveries from repository
        $incomingEvents = $this->repository->findEvents([
            'start_date' => $startDate,
        ], 0);

        $outgoingDeliveries = $this->repository->findDeliveries([
            'start_date' => $startDate,
        ], 0);

        // Calculate success rates
        $incomingTotal = $incomingEvents->count();
        $incomingSuccessful = $incomingEvents->where('processing_status', 'success')->count();
        $incomingSuccessRate = $incomingTotal > 0 ? round(($incomingSuccessful / $incomingTotal) * 100, 2) : 0;

        $outgoingTotal = $outgoingDeliveries->count();
        $outgoingSuccessful = $outgoingDeliveries->where('status', WebhookDelivery::STATUS_SUCCESS)->count();
        $outgoingSuccessRate = $outgoingTotal > 0 ? round(($outgoingSuccessful / $outgoingTotal) * 100, 2) : 0;

        // Calculate average response/processing times
        $outgoingAvgResponseTime = $outgoingDeliveries
            ->whereNotNull('response_time_ms')
            ->avg('response_time_ms');

        $incomingAvgProcessingTime = $incomingEvents
            ->whereNotNull('processing_time_ms')
            ->avg('processing_time_ms');

        // Calculate retry metrics
        $outgoingRetried = $outgoingDeliveries
            ->where('attempt', '>', 1)
            ->count();

        $outgoingRetryRate = $outgoingTotal > 0 ? round(($outgoingRetried / $outgoingTotal) * 100, 2) : 0;

        $outgoingAvgAttempts = $outgoingDeliveries->avg('attempt');

        // Calculate daily metrics
        $dailyStats = [];
        $today = now()->endOfDay();

        for ($i = 0; $i < $days; $i++) {
            $date = $today->copy()->subDays($i)->format('Y-m-d');
            $startOfDay = $today->copy()->subDays($i)->startOfDay();
            $endOfDay = $today->copy()->subDays($i)->endOfDay();

            $incomingDay = $incomingEvents->filter(function ($event) use ($startOfDay, $endOfDay) {
                return $event->created_at >= $startOfDay && $event->created_at <= $endOfDay;
            });

            $outgoingDay = $outgoingDeliveries->filter(function ($delivery) use ($startOfDay, $endOfDay) {
                return $delivery->created_at >= $startOfDay && $delivery->created_at <= $endOfDay;
            });

            $dailyStats[$date] = [
                'incoming' => [
                    'total' => $incomingDay->count(),
                    'success' => $incomingDay->where('processing_status', 'success')->count(),
                    'error' => $incomingDay->where('processing_status', 'error')->count(),
                ],
                'outgoing' => [
                    'total' => $outgoingDay->count(),
                    'success' => $outgoingDay->where('status', WebhookDelivery::STATUS_SUCCESS)->count(),
                    'failed' => $outgoingDay->where('status', WebhookDelivery::STATUS_FAILED)->count(),
                ],
            ];
        }

        // Return metrics
        return [
            'period' => [
                'days' => $days,
                'start_date' => $startDate->toDateTimeString(),
                'end_date' => now()->toDateTimeString(),
            ],
            'success_rates' => [
                'incoming' => $incomingSuccessRate,
                'outgoing' => $outgoingSuccessRate,
            ],
            'performance' => [
                'incoming_avg_processing_time_ms' => round($incomingAvgProcessingTime, 2),
                'outgoing_avg_response_time_ms' => round($outgoingAvgResponseTime, 2),
            ],
            'retry_metrics' => [
                'retry_rate' => $outgoingRetryRate,
                'avg_attempts' => round($outgoingAvgAttempts, 2),
                'retried_count' => $outgoingRetried,
            ],
            'daily' => $dailyStats,
        ];
    }

    /**
     * Retry failed webhook deliveries.
     *
     * @param array $criteria Criteria to select deliveries to retry
     *
     * @return int Number of deliveries queued for retry
     */
    final public function retryFailed(array $criteria = []): int
    {
        // Get failed deliveries
        $failedDeliveries = $this->repository->findDeliveries(array_merge([
            'status' => WebhookDelivery::STATUS_FAILED,
        ], $criteria), 0);

        $count = 0;

        // Retry each delivery that can be retried
        foreach ($failedDeliveries as $delivery) {
            try {
                if ($delivery->canBeRetried()) {
                    $this->dispatcher->retry($delivery, ['queue' => true]);
                    $count++;
                }
            } catch (\Throwable $e) {
                // Log the error but continue with other deliveries
                \Illuminate\Support\Facades\Log::error("Error retrying webhook delivery {$delivery->uuid}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }
}
