<?php

namespace WizardingCode\WebhookOwlery\Listeners;

use Illuminate\Support\Facades\Log;
use WizardingCode\WebhookOwlery\Events\WebhookHandled;

class LogWebhookHandled
{
    /**
     * Handle the event.
     */
    final public function handle(WebhookHandled $event): void
    {
        if (! config('webhook-owlery.monitoring.log_events', true)) {
            return;
        }

        $webhookEvent = $event->webhookEvent;

        Log::channel(config('webhook-owlery.monitoring.log_channel', 'webhook'))
            ->info("Webhook processed successfully: {$webhookEvent->source}.{$webhookEvent->event}", [
                'source' => $webhookEvent->source,
                'event_type' => $webhookEvent->event,
                'webhook_id' => $webhookEvent->uuid,
                'processing_time_ms' => $webhookEvent->processing_time_ms,
            ]);
    }
}
