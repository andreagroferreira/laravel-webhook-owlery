<?php

namespace WizardingCode\WebhookOwlery\Listeners;

use Illuminate\Support\Facades\Log;
use JsonException;
use WizardingCode\WebhookOwlery\Events\WebhookDispatching;
use WizardingCode\WebhookOwlery\Support\WebhookUtils;

class LogWebhookDispatching
{
    /**
     * Handle the event.
     *
     * @throws JsonException
     */
    final public function handle(WebhookDispatching $event): void
    {
        if (! config('webhook-owlery.monitoring.log_events', true)) {
            return;
        }

        $delivery = $event->delivery;
        $endpoint = $delivery->endpoint;

        $endpointName = $endpoint ? $endpoint->name : 'Custom URL';

        // Sanitize the payload for logging
        $sanitizedPayload = WebhookUtils::sanitizePayload($delivery->payload);

        Log::channel(config('webhook-owlery.monitoring.log_channel', 'webhook'))
            ->info("Dispatching webhook: {$delivery->event} to {$endpointName}", [
                'webhook_id' => $delivery->uuid,
                'event_type' => $delivery->event,
                'destination' => $delivery->destination,
                'endpoint_id' => $endpoint ? $endpoint->id : null,
                'payload_size' => strlen(json_encode($delivery->payload, JSON_THROW_ON_ERROR)),
                'payload' => $sanitizedPayload,
                'attempt' => $delivery->attempt,
            ]);
    }
}
