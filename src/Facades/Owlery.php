<?php

namespace WizardingCode\WebhookOwlery\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \WizardingCode\WebhookOwlery\Contracts\WebhookReceiverContract receiver()
 * @method static \WizardingCode\WebhookOwlery\Contracts\WebhookDispatcherContract dispatcher()
 * @method static \WizardingCode\WebhookOwlery\Contracts\WebhookRepositoryContract repository()
 * @method static \WizardingCode\WebhookOwlery\Contracts\WebhookSubscriptionContract subscriptions()
 * @method static \WizardingCode\WebhookOwlery\Contracts\CircuitBreakerContract circuitBreaker()
 * @method static \WizardingCode\WebhookOwlery\Models\WebhookEndpoint createEndpoint(string $name, string $url, array $events = [], array $options = [])
 * @method static \WizardingCode\WebhookOwlery\Models\WebhookSubscription subscribe($endpoint, string $eventType, array $filters = [], array $options = [])
 * @method static \WizardingCode\WebhookOwlery\Models\WebhookEvent handleIncoming(string $source, \Illuminate\Http\Request $request)
 * @method static \WizardingCode\WebhookOwlery\Models\WebhookDelivery sendWebhook(string $url, string $event, array $payload, array $options = [])
 * @method static array broadcastEvent(string $event, array $payload, array $options = [])
 * @method static self on(string $source, string $event, callable $handler)
 * @method static \WizardingCode\WebhookOwlery\Contracts\SignatureValidatorContract getSignatureValidator(string $type = 'hmac')
 * @method static array cleanup(?int $daysToKeep = null)
 * @method static array stats(int $days = 30)
 * @method static array metrics(int $days = 30)
 * @method static int retryFailed(array $criteria = [])
 *
 * @see \WizardingCode\WebhookOwlery\Contracts\OwleryContract
 */
class Owlery extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'webhook-owlery';
    }
}
