<?php

namespace WizardingCode\WebhookOwlery\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;
use WizardingCode\WebhookOwlery\Facades\Owlery;

class CleanupWebhookData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of days to keep webhook data.
     */
    private ?int $days;

    /**
     * Create a new job instance.
     *
     * @param int|null $days Number of days to keep data (null = use config)
     *
     * @return void
     */
    public function __construct(?int $days = null)
    {
        $this->days = $days;
        $this->onQueue(config('webhook-owlery.storage.cleanup_queue', 'default'));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = microtime(true);

        Log::info('Starting scheduled webhook data cleanup', [
            'days' => $this->days ?? config('webhook-owlery.storage.retention_days', 30),
        ]);

        try {
            // Run the cleanup
            $stats = Owlery::cleanup($this->days);

            $executionTime = round(microtime(true) - $startTime, 2);

            Log::info('Webhook data cleanup completed', [
                'events_deleted' => $stats['events_deleted'] ?? 0,
                'deliveries_deleted' => $stats['deliveries_deleted'] ?? 0,
                'execution_time' => $executionTime . 's',
            ]);
        } catch (Throwable $e) {
            Log::error('Error during webhook data cleanup', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->fail($e);
        }
    }

    /**
     * Handle a job failure.
     */
    private function failed(Throwable $exception): void
    {
        Log::error('Webhook data cleanup job failed', [
            'error' => $exception->getMessage(),
        ]);
    }
}
