<?php

namespace WizardingCode\WebhookOwlery\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use WizardingCode\WebhookOwlery\Models\WebhookDelivery;

/**
 * Event fired before a webhook is dispatched
 */
class WebhookDispatching
{
    use Dispatchable, SerializesModels;

    /**
     * The webhook delivery model.
     */
    public WebhookDelivery $delivery;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(WebhookDelivery $delivery)
    {
        $this->delivery = $delivery;
    }
}
