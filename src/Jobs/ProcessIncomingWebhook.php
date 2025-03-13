<?php

namespace WizardingCode\WebhookOwlery\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use WizardingCode\WebhookOwlery\Contracts\WebhookReceiverContract;
use WizardingCode\WebhookOwlery\Models\WebhookEvent;

class ProcessIncomingWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout;

    /**
     * The webhook event to process.
     */
    protected WebhookEvent $webhookEvent;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(WebhookEvent $webhookEvent)
    {
        $this->webhookEvent = $webhookEvent;
        $this->tries = config('webhook-owlery.receiving.max_attempts', 3);
        $this->timeout = config('webhook-owlery.receiving.timeout', 60);
        $this->onQueue(config('webhook-owlery.receiving.queue', 'default'));
    }

    /**
     * The unique ID of the job.
     */
    public function uniqueId(): string
    {
        return 'webhook-event-' . $this->webhookEvent->uuid;
    }

    /**
     * Execute the job.
     */
    public function handle(WebhookReceiverContract $receiver): void
    {
        try {
            Log::debug('Processing incoming webhook', [
                'webhook_id' => $this->webhookEvent->uuid,
                'source' => $this->webhookEvent->source,
                'event' => $this->webhookEvent->event,
            ]);

            // Check if webhook has already been processed
            if ($this->webhookEvent->is_processed) {
                Log::info('Webhook already processed, skipping', [
                    'webhook_id' => $this->webhookEvent->uuid,
                    'status' => $this->webhookEvent->processing_status,
                ]);

                return;
            }

            // Process the webhook
            $receiver->processWebhookEvent($this->webhookEvent);
        } catch (\Throwable $e) {
            Log::error('Error processing webhook', [
                'webhook_id' => $this->webhookEvent->uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Mark the webhook as processed with error
            $this->webhookEvent->markAsProcessed(
                'error',
                $e->getMessage(),
                null,
                get_class($e)
            );

            // Retry or fail based on configuration
            if ($this->attempts() >= $this->tries) {
                $this->fail($e);
            } else {
                $exponentialDelay = pow(2, $this->attempts()) * 10;
                $maxDelay = config('webhook-owlery.receiving.retry_delay_cap', 600);
                $delay = min($exponentialDelay, $maxDelay);

                $this->release($delay);
            }
        }
    }

    /**
     * Handle a job failure.
     */
    private function failed(\Throwable $exception): void
    {
        Log::error('Webhook processing failed after max attempts', [
            'webhook_id' => $this->webhookEvent->uuid,
            'source' => $this->webhookEvent->source,
            'event' => $this->webhookEvent->event,
            'error' => $exception->getMessage(),
        ]);

        // Update webhook status to permanent failure
        $this->webhookEvent->markAsProcessed(
            'failed',
            'Max retry attempts exceeded: ' . $exception->getMessage(),
            null,
            get_class($exception)
        );
    }
}
