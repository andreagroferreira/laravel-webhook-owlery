<?php

namespace WizardingCode\WebhookOwlery\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a webhook's signature validation fails
 */
class WebhookInvalid
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
     * The validation error message.
     */
    public ?string $message;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(string $source, string $event, Request $request, ?string $message = null)
    {
        $this->source = $source;
        $this->event = $event;
        $this->request = $request;
        $this->message = $message;
    }
}
