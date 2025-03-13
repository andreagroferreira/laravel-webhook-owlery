<?php

namespace WizardingCode\WebhookOwlery\Tests\Mocks;

use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;

class DispatchOutgoingWebhookMock
{
    use Dispatchable, Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $url,
        public string $event,
        public array $payload,
        public array $options = []
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Mock implementation for testing
    }
}
