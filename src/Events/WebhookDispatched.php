<?php

namespace WizardingCode\WebhookOwlery\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Psr\Http\Message\ResponseInterface;
use WizardingCode\WebhookOwlery\Models\WebhookDelivery;

/**
 * Event fired when a webhook has been successfully dispatched
 */
class WebhookDispatched
{
    use Dispatchable, SerializesModels;

    /**
     * The webhook delivery model.
     */
    public WebhookDelivery $delivery;

    /**
     * The HTTP response from the destination.
     *
     * @var ResponseInterface
     */
    public $response;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(WebhookDelivery $delivery, ResponseInterface $response)
    {
        $this->delivery = $delivery;
        $this->response = $response;
    }
}
