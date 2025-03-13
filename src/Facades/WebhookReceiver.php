<?php

namespace WizardingCode\WebhookOwlery\Facades;

use Illuminate\Support\Facades\Facade;
use WizardingCode\WebhookOwlery\Contracts\WebhookReceiverContract;

/**
 * @method static mixed configureEndpoint(string $source, string $endpoint, array $options = [])
 * @method static mixed handleRequest(string $source, \Illuminate\Http\Request $request)
 * @method static mixed on(string $source, string $event, $handler)
 * @method static mixed onAny(string $source, $handler)
 * @method static mixed before(string $source, $handler)
 * @method static mixed after(string $source, $handler)
 * @method static bool verifySignature(string $source, \Illuminate\Http\Request $request)
 * @method static callable|null getHandler(string $source, string $event)
 *
 * @see \WizardingCode\WebhookOwlery\Contracts\WebhookReceiverContract
 */
class WebhookReceiver extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return WebhookReceiverContract::class;
    }
}
