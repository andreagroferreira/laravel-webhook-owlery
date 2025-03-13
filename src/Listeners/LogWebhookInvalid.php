<?php

namespace WizardingCode\WebhookOwlery\Listeners;

use Illuminate\Support\Facades\Log;
use WizardingCode\WebhookOwlery\Events\WebhookInvalid;

class LogWebhookInvalid
{
    /**
     * Handle the event.
     */
    final public function handle(WebhookInvalid $event): void
    {
        if (! config('webhook-owlery.monitoring.log_events', true)) {
            return;
        }

        Log::channel(config('webhook-owlery.monitoring.log_channel', 'webhook'))
            ->warning("Invalid webhook signature for {$event->source}.{$event->event}", [
                'source' => $event->source,
                'event_type' => $event->event,
                'ip' => $event->request->ip(),
                'message' => $event->message,
            ]);

        // Alert through configured channels
        $this->sendAlerts($event);
    }

    /**
     * Send alerts about invalid webhooks if configured.
     */
    private function sendAlerts(WebhookInvalid $event): void
    {
        // Check if Slack alerts are enabled
        if (config('webhook-owlery.monitoring.alerts.slack.enabled', false)) {
            $this->sendSlackAlert($event);
        }
    }

    /**
     * Send Slack alert about invalid webhook.
     */
    private function sendSlackAlert(WebhookInvalid $event): void
    {
        $webhookUrl = config('webhook-owlery.monitoring.alerts.slack.webhook_url');

        if (empty($webhookUrl)) {
            return;
        }

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
                    'text' => '⚠️ *Invalid Webhook Signature*',
                    'attachments' => [
                        [
                            'color' => 'danger',
                            'fields' => [
                                [
                                    'title' => 'Source',
                                    'value' => $event->source,
                                    'short' => true,
                                ],
                                [
                                    'title' => 'Event',
                                    'value' => $event->event,
                                    'short' => true,
                                ],
                                [
                                    'title' => 'IP Address',
                                    'value' => $event->request->ip(),
                                    'short' => true,
                                ],
                                [
                                    'title' => 'Error',
                                    'value' => $event->message ?: 'Signature validation failed',
                                    'short' => false,
                                ],
                            ],
                            'ts' => time(),
                        ],
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send Slack alert for invalid webhook', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
