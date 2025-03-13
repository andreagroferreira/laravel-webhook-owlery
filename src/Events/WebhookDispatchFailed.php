<?php

namespace WizardingCode\WebhookOwlery\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;
use WizardingCode\WebhookOwlery\Models\WebhookDelivery;

/**
 * Event fired when a webhook dispatch fails
 */
class WebhookDispatchFailed
{
    use Dispatchable, SerializesModels;

    /**
     * The webhook delivery model.
     */
    public WebhookDelivery $delivery;

    /**
     * The exception that occurred during dispatch.
     */
    public Throwable $exception;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(WebhookDelivery $delivery, Throwable $exception)
    {
        $this->delivery = $delivery;
        $this->exception = $exception;
    }
}
