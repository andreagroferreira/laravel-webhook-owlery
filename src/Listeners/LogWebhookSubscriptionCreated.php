<?php

namespace WizardingCode\WebhookOwlery\Listeners;

use Illuminate\Support\Facades\Log;
use WizardingCode\WebhookOwlery\Events\WebhookSubscriptionCreated;

class LogWebhookSubscriptionCreated
{
    /**
     * Handle the event.
     */
    final public function handle(WebhookSubscriptionCreated $event): void
    {
        if (! config('webhook-owlery.monitoring.log_events', true)) {
            return;
        }

        $subscription = $event->subscription;
        $endpoint = $subscription->endpoint;

        Log::channel(config('webhook-owlery.monitoring.log_channel', 'webhook'))
            ->info("Webhook subscription created: {$subscription->event_type}", [
                'subscription_id' => $subscription->uuid,
                'event_type' => $subscription->event_type,
                'endpoint_id' => $endpoint->id,
                'endpoint_name' => $endpoint->name,
                'created_by' => $subscription->created_by,
                'expires_at' => $subscription->expires_at?->toIso8601String(),
                'max_deliveries' => $subscription->max_deliveries,
            ]);
    }
}
