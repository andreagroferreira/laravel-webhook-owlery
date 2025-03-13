<?php

namespace WizardingCode\WebhookOwlery\Listeners;

use Illuminate\Support\Facades\Log;
use WizardingCode\WebhookOwlery\Events\WebhookReceived;
use WizardingCode\WebhookOwlery\Support\WebhookUtils;

class LogWebhookReceived
{
    /**
     * Handle the event.
     */
    final public function handle(WebhookReceived $event): void
    {
        if (! config('webhook-owlery.monitoring.log_events', true)) {
            return;
        }

        $source = $event->source;
        $ip = $event->request->ip();
        $headers = array_map(static function ($header) {
            return is_array($header) && count($header) === 1 ? $header[0] : $header;
        }, $event->request->headers->all());

        // Sanitize headers to remove sensitive information
        $sanitizedHeaders = WebhookUtils::sanitizeHeaders($headers);

        // Extract event type if available
        $eventType = WebhookUtils::extractEventName($event->request, $source);

        Log::channel(config('webhook-owlery.monitoring.log_channel', 'webhook'))
            ->info("Webhook received from {$source}", [
                'source' => $source,
                'event_type' => $eventType,
                'ip' => $ip,
                'method' => $event->request->method(),
                'headers' => $sanitizedHeaders,
            ]);
    }
}
