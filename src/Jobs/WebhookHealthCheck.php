<?php

namespace WizardingCode\WebhookOwlery\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use WizardingCode\WebhookOwlery\Contracts\CircuitBreakerContract;
use WizardingCode\WebhookOwlery\Contracts\WebhookRepositoryContract;
use WizardingCode\WebhookOwlery\Models\WebhookDelivery;

class WebhookHealthCheck implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of hours to look back for stats.
     */
    protected int $hoursAgo;

    /**
     * The threshold percentage for failed webhooks to trigger an alert.
     */
    protected float $failureThreshold;

    /**
     * Options for alert notifications.
     */
    protected array $notificationOptions;

    /**
     * Create a new job instance.
     *
     * @param int   $hoursAgo            Hours to look back for stats
     * @param float $failureThreshold    Percentage threshold for alerting
     * @param array $notificationOptions Options for notifications
     *
     * @return void
     */
    public function __construct(
        int $hoursAgo = 24,
        float $failureThreshold = 10.0,
        array $notificationOptions = []
    ) {
        $this->hoursAgo = $hoursAgo;
        $this->failureThreshold = $failureThreshold;
        $this->notificationOptions = $notificationOptions;
        $this->onQueue(config('webhook-owlery.monitoring.queue', 'default'));
    }

    /**
     * Execute the job.
     */
    public function handle(WebhookRepositoryContract $repository, CircuitBreakerContract $circuitBreaker): void
    {
        Log::debug('Running webhook health check');

        try {
            // Get metrics for the last X hours
            $metrics = $this->collectMetrics($repository, $this->hoursAgo);

            // Log metrics summary
            Log::info('Webhook health metrics', [
                'period_hours' => $this->hoursAgo,
                'total_incoming' => $metrics['incoming_total'],
                'total_outgoing' => $metrics['outgoing_total'],
                'success_rate_incoming' => $metrics['incoming_success_rate'] . '%',
                'success_rate_outgoing' => $metrics['outgoing_success_rate'] . '%',
                'open_circuits' => $metrics['open_circuits'],
            ]);

            // Check if any metrics exceed thresholds
            $this->checkThresholds($metrics);

            // Check circuit breakers
            $this->checkCircuitBreakers($metrics, $circuitBreaker);
        } catch (\Throwable $e) {
            Log::error('Error in webhook health check', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->fail($e);
        }
    }

    /**
     * Collect health metrics from the repository.
     */
    protected function collectMetrics(WebhookRepositoryContract $repository, int $hoursAgo): array
    {
        $startDate = now()->subHours($hoursAgo);

        // Get incoming events
        $incomingEvents = $repository->findEvents([
            'start_date' => $startDate,
        ], 0);

        // Get outgoing deliveries
        $outgoingDeliveries = $repository->findDeliveries([
            'start_date' => $startDate,
        ], 0);

        // Get endpoints with open circuits
        $endpoints = $repository->findEndpoints();
        $openCircuits = [];

        // Calculate success rates
        $incomingTotal = $incomingEvents->count();
        $incomingSuccessful = $incomingEvents->where('processing_status', 'success')->count();
        $incomingSuccessRate = $incomingTotal > 0
            ? round(($incomingSuccessful / $incomingTotal) * 100, 2)
            : 100;

        $outgoingTotal = $outgoingDeliveries->count();
        $outgoingSuccessful = $outgoingDeliveries->where('status', WebhookDelivery::STATUS_SUCCESS)->count();
        $outgoingSuccessRate = $outgoingTotal > 0
            ? round(($outgoingSuccessful / $outgoingTotal) * 100, 2)
            : 100;

        // Get top failing endpoints
        $failingEndpoints = [];
        if ($outgoingTotal > 0) {
            $endpointStats = $outgoingDeliveries
                ->groupBy('webhook_endpoint_id')
                ->map(function ($group) {
                    $total = $group->count();
                    $failed = $group->where('status', WebhookDelivery::STATUS_FAILED)->count();
                    $failureRate = $total > 0 ? round(($failed / $total) * 100, 2) : 0;

                    return [
                        'endpoint_id' => $group->first()->webhook_endpoint_id,
                        'total' => $total,
                        'failed' => $failed,
                        'failure_rate' => $failureRate,
                    ];
                })
                ->sortByDesc('failure_rate')
                ->values()
                ->take(5);

            foreach ($endpointStats as $stat) {
                if ($stat['failure_rate'] > 0) {
                    $failingEndpoints[] = $stat;
                }
            }
        }

        return [
            'incoming_total' => $incomingTotal,
            'incoming_successful' => $incomingSuccessful,
            'incoming_success_rate' => $incomingSuccessRate,
            'outgoing_total' => $outgoingTotal,
            'outgoing_successful' => $outgoingSuccessful,
            'outgoing_success_rate' => $outgoingSuccessRate,
            'start_date' => $startDate->toDateTimeString(),
            'end_date' => now()->toDateTimeString(),
            'failing_endpoints' => $failingEndpoints,
            'open_circuits' => $openCircuits,
        ];
    }

    /**
     * Check if any metrics exceed thresholds.
     */
    protected function checkThresholds(array $metrics): void
    {
        $failureThreshold = $this->failureThreshold;

        // Check outgoing success rate
        $outgoingFailureRate = 100 - $metrics['outgoing_success_rate'];
        if ($metrics['outgoing_total'] > 10 && $outgoingFailureRate >= $failureThreshold) {
            Log::warning('Webhook outgoing failure rate exceeds threshold', [
                'failure_rate' => $outgoingFailureRate . '%',
                'threshold' => $failureThreshold . '%',
                'total_webhooks' => $metrics['outgoing_total'],
            ]);

            $this->sendAlert('Webhook Failure Rate Alert',
                "Outgoing webhook failure rate ({$outgoingFailureRate}%) exceeds threshold ({$failureThreshold}%).",
                $metrics
            );
        }

        // Check incoming success rate
        $incomingFailureRate = 100 - $metrics['incoming_success_rate'];
        if ($metrics['incoming_total'] > 10 && $incomingFailureRate >= $failureThreshold) {
            Log::warning('Webhook incoming failure rate exceeds threshold', [
                'failure_rate' => $incomingFailureRate . '%',
                'threshold' => $failureThreshold . '%',
                'total_webhooks' => $metrics['incoming_total'],
            ]);

            $this->sendAlert('Webhook Processing Failure Alert',
                "Incoming webhook processing failure rate ({$incomingFailureRate}%) exceeds threshold ({$failureThreshold}%).",
                $metrics
            );
        }

        // Check for failing endpoints
        foreach ($metrics['failing_endpoints'] as $endpoint) {
            if ($endpoint['total'] > 5 && $endpoint['failure_rate'] >= $failureThreshold) {
                Log::warning('Endpoint has high failure rate', [
                    'endpoint_id' => $endpoint['endpoint_id'],
                    'failure_rate' => $endpoint['failure_rate'] . '%',
                    'threshold' => $failureThreshold . '%',
                    'total_webhooks' => $endpoint['total'],
                ]);

                $this->sendAlert('Endpoint Failure Alert',
                    "Endpoint #{$endpoint['endpoint_id']} has a high failure rate ({$endpoint['failure_rate']}%).",
                    ['endpoint' => $endpoint]
                );
            }
        }
    }

    /**
     * Check circuit breaker status.
     */
    protected function checkCircuitBreakers(array $metrics, CircuitBreakerContract $circuitBreaker): void
    {
        // Circuit breaker checks would go here when implemented
        // This requires getting all endpoints and checking circuit status
    }

    /**
     * Send an alert notification.
     */
    protected function sendAlert(string $title, string $message, array $data = []): void
    {
        // Log the alert
        Log::warning($title . ': ' . $message, $data);

        // Check if a notification class is configured
        $notificationClass = config('webhook-owlery.monitoring.notification_class');

        if ($notificationClass && class_exists($notificationClass)) {
            try {
                // Check for notification channels
                $channels = config('webhook-owlery.monitoring.channels', ['mail']);

                // Check for notification recipients
                $recipients = config('webhook-owlery.monitoring.recipients', []);

                if (! empty($recipients)) {
                    // Create notification instance
                    $notification = new $notificationClass($title, $message, $data);

                    // Send notification to each recipient
                    foreach ($recipients as $recipient) {
                        Notification::route($channels[0], $recipient)
                            ->notify($notification);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Failed to send webhook alert notification', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Webhook health check job failed', [
            'error' => $exception->getMessage(),
        ]);
    }
}
