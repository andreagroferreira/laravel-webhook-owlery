<?php

namespace WizardingCode\WebhookOwlery\Listeners;

use Illuminate\Support\Facades\Log;
use WizardingCode\WebhookOwlery\Events\WebhookDispatched;

class LogWebhookDispatched
{
    /**
     * Handle the event.
     */
    final public function handle(WebhookDispatched $event): void
    {
        if (! config('webhook-owlery.monitoring.log_events', true)) {
            return;
        }

        $delivery = $event->delivery;
        $response = $event->response;
        $endpoint = $delivery->endpoint;

        $endpointName = $endpoint ? $endpoint->name : 'Custom URL';
        $statusCode = $response->getStatusCode();

        Log::channel(config('webhook-owlery.monitoring.log_channel', 'webhook'))
            ->info("Webhook dispatched successfully: {$delivery->event} to {$endpointName}", [
                'webhook_id' => $delivery->uuid,
                'event_type' => $delivery->event,
                'destination' => $delivery->destination,
                'endpoint_id' => $endpoint ? $endpoint->id : null,
                'status_code' => $statusCode,
                'response_time_ms' => $delivery->response_time_ms,
                'attempt' => $delivery->attempt,
            ]);
    }
}
