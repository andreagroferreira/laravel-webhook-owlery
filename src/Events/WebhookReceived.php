<?php

namespace WizardingCode\WebhookOwlery\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a webhook is received but before validation or processing
 */
class WebhookReceived
{
    use Dispatchable, SerializesModels;

    /**
     * The source of the webhook.
     */
    public string $source;

    /**
     * The HTTP request containing the webhook data.
     */
    public Request $request;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(string $source, Request $request)
    {
        $this->source = $source;
        $this->request = $request;
    }
}
