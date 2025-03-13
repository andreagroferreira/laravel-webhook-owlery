<?php

namespace WizardingCode\WebhookOwlery\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;
use Throwable;
use WizardingCode\WebhookOwlery\Models\WebhookEvent;

/**
 * Event fired when a webhook processing fails
 */
class WebhookFailed
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
     * The exception that occurred during processing.
     */
    public Throwable $exception;

    /**
     * The webhook event model if already stored.
     */
    public ?WebhookEvent $webhookEvent;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(string $source, Request $request, Throwable $exception, ?WebhookEvent $webhookEvent = null)
    {
        $this->source = $source;
        $this->request = $request;
        $this->exception = $exception;
        $this->webhookEvent = $webhookEvent;
    }
}
