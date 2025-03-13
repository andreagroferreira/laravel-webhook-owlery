<?php

namespace WizardingCode\WebhookOwlery\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use WizardingCode\WebhookOwlery\Contracts\WebhookRepositoryContract;
use WizardingCode\WebhookOwlery\Models\WebhookDelivery;

class RetryFailedWebhooks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of hours to look back for failed webhooks.
     */
    protected int $hoursAgo;

    /**
     * Limit the number of webhooks to retry.
     */
    protected ?int $limit;

    /**
     * Options for the retry.
     */
    protected array $options;

    /**
     * Create a new job instance.
     *
     * @param int      $hoursAgo Look back period in hours
     * @param int|null $limit    Maximum number of webhooks to retry
     * @param array    $options  Additional retry options
     *
     * @return void
     */
    public function __construct(int $hoursAgo = 24, ?int $limit = 100, array $options = [])
    {
        $this->hoursAgo = $hoursAgo;
        $this->limit = $limit;
        $this->options = $options;
        $this->onQueue(config('webhook-owlery.dispatching.retry_queue', 'default'));
    }

    /**
     * Execute the job.
     */
    public function handle(WebhookRepositoryContract $repository): void
    {
        $startTime = microtime(true);

        Log::info('Starting automatic retry of failed webhooks', [
            'hours_ago' => $this->hoursAgo,
            'limit' => $this->limit,
        ]);

        // Find failed webhooks from the specified period
        $failedWebhooks = $repository->findDeliveries([
            'status' => WebhookDelivery::STATUS_FAILED,
            'start_date' => now()->subHours($this->hoursAgo),
            'limit' => $this->limit,
            'order_by' => 'created_at',
            'order_direction' => 'desc',
        ]);

        if ($failedWebhooks->isEmpty()) {
            Log::info('No failed webhooks found to retry');

            return;
        }

        Log::info("Found {$failedWebhooks->count()} failed webhooks to retry");

        $retried = 0;
        $skipped = 0;

        foreach ($failedWebhooks as $delivery) {
            try {
                if ($delivery->canBeRetried()) {
                    // Prepare retry options
                    $retryOptions = array_merge([
                        'queue' => true,
                        'reset_circuit' => true,
                    ], $this->options);

                    // Dispatch retry job
                    DispatchOutgoingWebhook::dispatch($delivery->id);

                    // Update status to retrying
                    $repository->updateDeliveryStatus($delivery, WebhookDelivery::STATUS_RETRYING);

                    // Add metadata about this retry
                    $metadata = $delivery->metadata ?? [];
                    $metadata['auto_retried_at'] = now()->toIso8601String();
                    $metadata['auto_retry_job'] = self::class;
                    $delivery->metadata = $metadata;
                    $delivery->save();

                    $retried++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                Log::error("Error retrying webhook {$delivery->uuid}", [
                    'error' => $e->getMessage(),
                ]);
                $skipped++;
            }
        }

        $executionTime = round(microtime(true) - $startTime, 2);

        Log::info('Automatic retry completed', [
            'retried' => $retried,
            'skipped' => $skipped,
            'execution_time' => $executionTime . 's',
        ]);
    }
}
