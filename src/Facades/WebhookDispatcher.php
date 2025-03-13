<?php

namespace WizardingCode\WebhookOwlery\Facades;

use Illuminate\Support\Facades\Facade;
use WizardingCode\WebhookOwlery\Contracts\WebhookDispatcherContract;
use WizardingCode\WebhookOwlery\Models\WebhookDelivery;

/**
 * @method static WebhookDelivery send(string $url, string $event, array $payload, array $options = [])
 * @method static WebhookDelivery sendToEndpoint($endpoint, string $event, array $payload, array $options = [])
 * @method static WebhookDelivery queue(string $url, string $event, array $payload, array $options = [])
 * @method static WebhookDelivery queueToEndpoint($endpoint, string $event, array $payload, array $options = [])
 * @method static array broadcast(string $event, array $payload, array $options = [])
 * @method static WebhookDelivery retry($delivery, ?array $options = null)
 * @method static WebhookDelivery cancel($delivery, ?string $reason = null)
 * @method static WebhookDispatcherContract beforeDispatch(callable $callback)
 * @method static WebhookDispatcherContract afterDispatch(callable $callback)
 *
 * @see \WizardingCode\WebhookOwlery\Contracts\WebhookDispatcherContract
 */
class WebhookDispatcher extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return WebhookDispatcherContract::class;
    }
}
