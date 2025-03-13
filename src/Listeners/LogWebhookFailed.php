<?php

namespace WizardingCode\WebhookOwlery\Listeners;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use WizardingCode\WebhookOwlery\Events\WebhookFailed;

class LogWebhookFailed
{
    /**
     * Handle the event.
     */
    final public function handle(WebhookFailed $event): void
    {
        if (! config('webhook-owlery.monitoring.log_events', true)) {
            return;
        }

        $source = $event->source;
        $webhookId = $event->webhookEvent->uuid ?? 'unknown';
        $eventType = $event->webhookEvent->event ?? 'unknown';

        Log::channel(config('webhook-owlery.monitoring.log_channel', 'webhook'))
            ->error("Webhook processing failed for {$source}.{$eventType}", [
                'source' => $source,
                'event_type' => $eventType,
                'webhook_id' => $webhookId,
                'error' => $event->exception->getMessage(),
                'exception' => get_class($event->exception),
                'trace' => $event->exception->getTraceAsString(),
            ]);

        // Alert through configured channels
        $this->sendAlerts($event);
    }

    /**
     * Send alerts about failed webhooks if configured.
     */
    private function sendAlerts(WebhookFailed $event): void
    {
        // Check if Slack alerts are enabled
        if (config('webhook-owlery.monitoring.alerts.slack.enabled', false)) {
            $this->sendSlackAlert($event);
        }
    }

    /**
     * Send Slack alert about failed webhook.
     */
    private function sendSlackAlert(WebhookFailed $event): void
    {
        $webhookUrl = config('webhook-owlery.monitoring.alerts.slack.webhook_url');

        if (empty($webhookUrl)) {
            return;
        }

        $source = $event->source;
        $webhookId = $event->webhookEvent->uuid ?? 'unknown';
        $eventType = $event->webhookEvent->event ?? 'unknown';

        $channel = config('webhook-owlery.monitoring.alerts.slack.channel', '#webhooks');
        $username = config('webhook-owlery.monitoring.alerts.slack.username', 'Webhook Owlery');
        $emoji = config('webhook-owlery.monitoring.alerts.slack.emoji', ':owl:');

        $client = new Client;

        try {
            $client->post($webhookUrl, [
                'json' => [
                    'channel' => $channel,
                    'username' => $username,
                    'icon_emoji' => $emoji,
                    'text' => '❌ *Webhook Processing Failed*',
                    'attachments' => [
                        [
                            'color' => 'danger',
                            'fields' => [
                                [
                                    'title' => 'Source',
                                    'value' => $source,
                                    'short' => true,
                                ],
                                [
                                    'title' => 'Event',
                                    'value' => $eventType,
                                    'short' => true,
                                ],
                                [
                                    'title' => 'Webhook ID',
                                    'value' => $webhookId,
                                    'short' => true,
                                ],
                                [
                                    'title' => 'Error',
                                    'value' => $event->exception->getMessage(),
                                    'short' => false,
                                ],
                                [
                                    'title' => 'Exception',
                                    'value' => get_class($event->exception),
                                    'short' => true,
                                ],
                            ],
                            'ts' => time(),
                        ],
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send Slack alert for failed webhook', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
