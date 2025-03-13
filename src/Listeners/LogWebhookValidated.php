<?php

namespace WizardingCode\WebhookOwlery\Listeners;

use Illuminate\Support\Facades\Log;
use WizardingCode\WebhookOwlery\Events\WebhookValidated;

class LogWebhookValidated
{
    /**
     * Handle the event.
     */
    final public function handle(WebhookValidated $event): void
    {
        if (! config('webhook-owlery.monitoring.log_events', true)) {
            return;
        }

        Log::channel(config('webhook-owlery.monitoring.log_channel', 'webhook'))
            ->info("Webhook signature validated for {$event->source}.{$event->event}", [
                'source' => $event->source,
                'event_type' => $event->event,
                'ip' => $event->request->ip(),
            ]);
    }
}
