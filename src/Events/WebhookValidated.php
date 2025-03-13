<?php

namespace WizardingCode\WebhookOwlery\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a webhook's signature has been validated successfully
 */
class WebhookValidated
{
    use Dispatchable, SerializesModels;

    /**
     * The source of the webhook.
     */
    public string $source;

    /**
     * The event type of the webhook.
     */
    public string $event;

    /**
     * The HTTP request containing the webhook data.
     */
    public Request $request;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(string $source, string $event, Request $request)
    {
        $this->source = $source;
        $this->event = $event;
        $this->request = $request;
    }
}
