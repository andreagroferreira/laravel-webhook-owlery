<?php

namespace WizardingCode\WebhookOwlery\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use WizardingCode\WebhookOwlery\Contracts\WebhookDispatcherContract;
use WizardingCode\WebhookOwlery\Contracts\WebhookRepositoryContract;
use WizardingCode\WebhookOwlery\Exceptions\CircuitOpenException;
use WizardingCode\WebhookOwlery\Exceptions\WebhookDeliveryException;
use WizardingCode\WebhookOwlery\Models\WebhookDelivery;

class DispatchOutgoingWebhook implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 60;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array
     */
    public $backoff = [10, 60, 120, 300, 600];

    /**
     * The ID of the webhook delivery.
     */
    private int|string $deliveryId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int|string $deliveryId)
    {
        $this->deliveryId = $deliveryId;
        $this->configureJob();
    }

    /**
     * Configure job settings from config.
     */
    private function configureJob(): void
    {
        // Set default queue
        $this->onQueue(config('webhook-owlery.dispatching.queue', 'default'));

        // Set timeout
        $this->timeout = config('webhook-owlery.dispatching.timeout', 60);

        // Set max attempts to 1 by default - we handle retries differently
        // because we need to record delivery attempts and manage circuit breaker
        $this->tries = 1;

        // Backoff is handled by our retry mechanism but we set a default
        // for cases where Laravel's retry mechanism would be triggered
        $this->backoff = [10, 60, 120, 300, 600];
    }

    /**
     * Get the unique ID for the job.
     */
    private function uniqueId(): string
    {
        return 'webhook-delivery-' . $this->deliveryId;
    }

    /**
     * Execute the job.
     */
    public function handle(WebhookRepositoryContract $repository, WebhookDispatcherContract $dispatcher): void
    {
        try {
            // Get the delivery record
            $delivery = $repository->getDelivery($this->deliveryId);

            if (! $delivery) {
                Log::error('Webhook delivery not found', ['id' => $this->deliveryId]);

                return;
            }

            Log::debug('Dispatching webhook', [
                'delivery_id' => $delivery->uuid,
                'destination' => $delivery->destination,
                'event' => $delivery->event,
                'attempt' => $delivery->attempt,
            ]);

            // Check if delivery is already marked as successful or cancelled
            if (in_array($delivery->status, [WebhookDelivery::STATUS_SUCCESS, WebhookDelivery::STATUS_CANCELLED], true)) {
                Log::info('Webhook delivery already completed, skipping', [
                    'delivery_id' => $delivery->uuid,
                    'status' => $delivery->status,
                ]);

                return;
            }

            // Check if delivery has reached maximum attempts
            if ($delivery->attempt >= $delivery->max_attempts) {
                Log::warning('Webhook delivery reached maximum attempts', [
                    'delivery_id' => $delivery->uuid,
                    'attempts' => $delivery->attempt,
                    'max_attempts' => $delivery->max_attempts,
                ]);

                $repository->markDeliveryFailed(
                    $delivery,
                    null,
                    null,
                    null,
                    'Maximum retry attempts reached',
                    "Delivery failed after {$delivery->attempt} attempts"
                );

                return;
            }

            // Send the webhook
            $dispatcher->send(
                $delivery->destination,
                $delivery->event,
                $delivery->payload,
                [
                    'headers' => $delivery->headers ?? [],
                    'timeout' => $delivery->timeout ?? 30,
                    'delivery_id' => $delivery->id,
                ]
            );

            // If we get here, delivery was successful and marked in the send method
            Log::info('Webhook dispatched successfully', [
                'delivery_id' => $delivery->uuid,
                'destination' => $delivery->destination,
                'event' => $delivery->event,
            ]);
        } catch (CircuitOpenException $e) {
            // Circuit is open, schedule retry after reset timeout
            $resetTime = $e->getResetTimeout();
            $delaySeconds = max(5, $resetTime - time());

            Log::warning('Circuit breaker open, scheduling retry after reset', [
                'delivery_id' => $this->deliveryId,
                'reset_at' => date('Y-m-d H:i:s', $resetTime),
                'delay_seconds' => $delaySeconds,
            ]);

            // Re-queue the job to run after circuit resets
            $this->release($delaySeconds);
        } catch (WebhookDeliveryException $e) {
            // Handle delivery exceptions - these are already logged in the dispatcher
            // and the delivery record is updated with failure details

            // Get retry interval - could be custom per endpoint or use default
            $delivery = $repository->getDelivery($this->deliveryId);

            if ($delivery) {
                $retryIntervals = $this->getRetryIntervals($delivery);
                $currentAttempt = $delivery->attempt;

                if ($currentAttempt < $delivery->max_attempts && isset($retryIntervals[$currentAttempt - 1])) {
                    $delay = $retryIntervals[$currentAttempt - 1];

                    Log::info('Scheduling webhook retry', [
                        'delivery_id' => $delivery->uuid,
                        'attempt' => $currentAttempt,
                        'next_attempt' => $currentAttempt + 1,
                        'max_attempts' => $delivery->max_attempts,
                        'delay_seconds' => $delay,
                    ]);

                    // Schedule retry
                    // Update delivery status to retrying and set next attempt time in metadata
                    $metadata = $delivery->metadata ?? [];
                    $metadata['next_attempt_at'] = now()->addSeconds($delay)->toIso8601String();

                    $repository->updateDeliveryStatus(
                        $delivery,
                        WebhookDelivery::STATUS_RETRYING,
                        $metadata
                    );

                    // Re-dispatch with delay
                    self::dispatch($this->deliveryId)
                        ->delay(now()->addSeconds($delay))
                        ->onQueue(config('webhook-owlery.dispatching.queue', 'default'));
                }
            }
        } catch (\Throwable $e) {
            // Handle unexpected exceptions
            Log::error('Unexpected error dispatching webhook', [
                'delivery_id' => $this->deliveryId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update delivery record with error
            try {
                $delivery = $repository->getDelivery($this->deliveryId);
                if ($delivery) {
                    $repository->markDeliveryFailed(
                        $delivery,
                        null,
                        null,
                        null,
                        'Unexpected error: ' . $e->getMessage(),
                        $e->getTraceAsString()
                    );
                }
            } catch (\Throwable $innerException) {
                Log::error('Error updating delivery record after failure', [
                    'delivery_id' => $this->deliveryId,
                    'error' => $innerException->getMessage(),
                ]);
            }

            // Fail the job
            $this->fail($e);
        }
    }

    /**
     * Calculate the retry intervals for a webhook delivery.
     */
    protected function getRetryIntervals(WebhookDelivery $delivery): array
    {
        // If custom intervals are defined, use those
        if (! empty($delivery->retry_intervals) && is_array($delivery->retry_intervals)) {
            return $delivery->retry_intervals;
        }

        // Otherwise use the default retry strategy
        $strategy = config('webhook-owlery.dispatching.retry_strategy', 'exponential');
        $baseDelay = config('webhook-owlery.dispatching.retry_delay', 30);
        $maxAttempts = $delivery->max_attempts ?? config('webhook-owlery.dispatching.max_attempts', 3);

        $intervals = [];

        switch ($strategy) {
            case 'linear':
                // Linear backoff: delay * attempt
                for ($i = 1; $i < $maxAttempts; $i++) {
                    $intervals[] = $baseDelay * $i;
                }
                break;

            case 'exponential':
                // Exponential backoff: delay * 2^(attempt-1)
                for ($i = 1; $i < $maxAttempts; $i++) {
                    $intervals[] = $baseDelay * pow(2, $i - 1);
                }
                break;

            case 'fixed':
                // Fixed delay
                for ($i = 1; $i < $maxAttempts; $i++) {
                    $intervals[] = $baseDelay;
                }
                break;

            default:
                // Default to exponential
                for ($i = 1; $i < $maxAttempts; $i++) {
                    $intervals[] = $baseDelay * pow(2, $i - 1);
                }
        }

        return $intervals;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Webhook dispatch job failed', [
            'delivery_id' => $this->deliveryId,
            'error' => $exception->getMessage(),
        ]);
    }
}
