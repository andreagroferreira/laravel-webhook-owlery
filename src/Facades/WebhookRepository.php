<?php

namespace WizardingCode\WebhookOwlery\Facades;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use WizardingCode\WebhookOwlery\Contracts\WebhookRepositoryContract;
use WizardingCode\WebhookOwlery\Models\WebhookDelivery;
use WizardingCode\WebhookOwlery\Models\WebhookEndpoint;
use WizardingCode\WebhookOwlery\Models\WebhookEvent;
use WizardingCode\WebhookOwlery\Models\WebhookSubscription;

/**
 * @method static WebhookEvent storeIncomingEvent(string $source, string $event, array $payload, \Illuminate\Http\Request $request, array $metadata = [])
 * @method static WebhookDelivery storeOutgoingDelivery(string $event, string $destination, array $payload, array $options = [], ?WebhookEndpoint $endpoint = null)
 * @method static WebhookDelivery updateDeliveryStatus($delivery, string $status, array $metadata = [])
 * @method static WebhookDelivery markDeliverySucceeded($delivery, int $statusCode, ?string $responseBody = null, ?array $responseHeaders = null, ?int $responseTime = null)
 * @method static WebhookDelivery markDeliveryFailed($delivery, ?int $statusCode = null, ?string $responseBody = null, ?array $responseHeaders = null, ?string $errorMessage = null, ?string $errorDetail = null, ?int $responseTime = null)
 * @method static Collection|LengthAwarePaginator findDeliveries(array $criteria, int $perPage = 15)
 * @method static Collection|LengthAwarePaginator findEvents(array $criteria, int $perPage = 15)
 * @method static Collection|LengthAwarePaginator findEndpoints(array $criteria, int $perPage = 15)
 * @method static Collection|LengthAwarePaginator findSubscriptions(array $criteria, int $perPage = 15)
 * @method static Collection findEndpointsForEvent(string $eventType, bool $activeOnly = true)
 * @method static Collection findSubscriptionsForEvent(string $eventType, array $eventData = [], bool $activeOnly = true)
 * @method static WebhookDelivery|null getDelivery($id)
 * @method static WebhookEvent|null getEvent($id)
 * @method static WebhookEndpoint|null getEndpoint($id)
 * @method static WebhookSubscription|null getSubscription($id)
 * @method static WebhookEndpoint createEndpoint(array $data)
 * @method static WebhookSubscription createSubscription($endpoint, string $eventType, array $filters = [], array $options = [])
 * @method static WebhookEndpoint updateEndpoint($endpoint, array $data)
 * @method static WebhookSubscription updateSubscription($subscription, array $data)
 * @method static bool deleteEndpoint($endpoint, bool $force = false)
 * @method static bool deleteSubscription($subscription, bool $force = false)
 * @method static array cleanupOldData(?int $daysToKeep = null)
 *
 * @see \WizardingCode\WebhookOwlery\Contracts\WebhookRepositoryContract
 */
class WebhookRepository extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return WebhookRepositoryContract::class;
    }
}
