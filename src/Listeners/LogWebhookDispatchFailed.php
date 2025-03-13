<?php

namespace WizardingCode\WebhookOwlery\Listeners;

use Illuminate\Support\Facades\Log;
use WizardingCode\WebhookOwlery\Events\WebhookDispatchFailed;
use WizardingCode\WebhookOwlery\Models\WebhookDelivery;

class LogWebhookDispatchFailed
{
    /**
     * Handle the event.
     */
    final public function handle(WebhookDispatchFailed $event): void
    {
        if (! config('webhook-owlery.monitoring.log_events', true)) {
            return;
        }

        $delivery = $event->delivery;
        $exception = $event->exception;
        $endpoint = $delivery->endpoint;

        $endpointName = $endpoint ? $endpoint->name : 'Custom URL';

        Log::channel(config('webhook-owlery.monitoring.log_channel', 'webhook'))
            ->error("Webhook dispatch failed: {$delivery->event} to {$endpointName}", [
                'webhook_id' => $delivery->uuid,
                'event_type' => $delivery->event,
                'destination' => $delivery->destination,
                'endpoint_id' => $endpoint ? $endpoint->id : null,
                'error' => $exception->getMessage(),
                'exception' => get_class($exception),
                'status_code' => $delivery->status_code,
                'attempt' => $delivery->attempt,
                'max_attempts' => $delivery->max_attempts,
            ]);

        // Alert through configured channels
        $this->sendAlerts($event);

        // Check if this was the last allowed attempt
        if ($delivery->attempt >= $delivery->max_attempts) {
            $this->logMaxAttemptsReached($delivery);
        }
    }

    /**
     * Log when max attempts are reached.
     */
    private function logMaxAttemptsReached(WebhookDelivery $delivery): void
    {
        $endpoint = $delivery->endpoint;
        $endpointName = $endpoint ? $endpoint->name : 'Custom URL';

        Log::channel(config('webhook-owlery.monitoring.log_channel', 'webhook'))
            ->warning("Maximum retry attempts reached for webhook: {$delivery->event} to {$endpointName}", [
                'webhook_id' => $delivery->uuid,
                'event_type' => $delivery->event,
                'destination' => $delivery->destination,
                'endpoint_id' => $endpoint ? $endpoint->id : null,
                'attempts' => $delivery->attempt,
                'max_attempts' => $delivery->max_attempts,
            ]);
    }

    /**
     * Send alerts about failed dispatches if configured.
     */
    private function sendAlerts(WebhookDispatchFailed $event): void
    {
        // Check if Slack alerts are enabled
        if (config('webhook-owlery.monitoring.alerts.slack.enabled', false)) {
            $this->sendSlackAlert($event);
        }
    }

    /**
     * Send Slack alert about failed dispatch.
     */
    private function sendSlackAlert(WebhookDispatchFailed $event): void
    {
        $webhookUrl = config('webhook-owlery.monitoring.alerts.slack.webhook_url');

        if (empty($webhookUrl)) {
            return;
        }

        $delivery = $event->delivery;
        $exception = $event->exception;
        $endpoint = $delivery->endpoint;

        $endpointName = $endpoint ? $endpoint->name : 'Custom URL';

        $channel = config('webhook-owlery.monitoring.alerts.slack.channel', '#webhooks');
        $username = config('webhook-owlery.monitoring.alerts.slack.username', 'Webhook Owlery');
        $emoji = config('webhook-owlery.monitoring.alerts.slack.emoji', ':owl:');

        $client = new \GuzzleHttp\Client;

        try {
            $client->post($webhookUrl, [
                'json' => [
                    'channel' => $channel,
                    'username' => $username,
                    'icon_emoji' => $emoji,
                    'text' => '❌ *Webhook Dispatch Failed*',
                    'attachments' => [
                        [
                            'color' => 'danger',
                            'fields' => [
                                [
                                    'title' => 'Event',
                                    'value' => $delivery->event,
                                    'short' => true,
                                ],
                                [
                                    'title' => 'Destination',
                                    'value' => $endpointName,
                                    'short' => true,
                                ],
                                [
                                    'title' => 'Webhook ID',
                                    'value' => $delivery->uuid,
                                    'short' => true,
                                ],
                                [
                                    'title' => 'Attempt',
                                    'value' => "{$delivery->attempt} of {$delivery->max_attempts}",
                                    'short' => true,
                                ],
                                [
                                    'title' => 'Error',
                                    'value' => $exception->getMessage(),
                                    'short' => false,
                                ],
                                [
                                    'title' => 'Status Code',
                                    'value' => $delivery->status_code ?: 'N/A',
                                    'short' => true,
                                ],
                            ],
                            'ts' => time(),
                        ],
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send Slack alert for failed webhook dispatch', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
