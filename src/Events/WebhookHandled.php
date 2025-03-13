<?php

namespace WizardingCode\WebhookOwlery\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use WizardingCode\WebhookOwlery\Models\WebhookEvent;

/**
 * Event fired when a webhook has been successfully processed
 */
class WebhookHandled
{
    use Dispatchable, SerializesModels;

    /**
     * The webhook event model.
     */
    public WebhookEvent $webhookEvent;

    /**
     * The result of the webhook processing.
     */
    public mixed $result;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(WebhookEvent $webhookEvent, mixed $result)
    {
        $this->webhookEvent = $webhookEvent;
        $this->result = $result;
    }
}
