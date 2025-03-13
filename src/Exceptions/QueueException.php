<?php

namespace WizardingCode\WebhookOwlery\Exceptions;

use Throwable;

class QueueException extends OwleryException
{
    protected ?string $queue;

    protected ?string $jobId;

    /**
     * Create a new queue exception instance.
     *
     * @return void
     */
    public function __construct(
        string $message = 'Webhook queue error',
        ?string $queue = null,
        ?string $jobId = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->queue = $queue;
        $this->jobId = $jobId;

        $fullMessage = $message;

        if ($queue) {
            $fullMessage .= " on queue '{$queue}'";
        }

        if ($jobId) {
            $fullMessage .= " (job ID: {$jobId})";
        }

        parent::__construct($fullMessage, $code, $previous);
    }

    /**
     * Get the queue name.
     */
    final public function getQueue(): ?string
    {
        return $this->queue;
    }

    /**
     * Get the job ID.
     */
    final public function getJobId(): ?string
    {
        return $this->jobId;
    }

    /**
     * Create a new exception for a job that exceeded max attempts.
     *
     * @return static
     */
    public static function maxAttemptsExceeded(?string $queue = null, ?string $jobId = null, int $attempts = 0, int $code = 0, ?Throwable $previous = null): self
    {
        $message = "Maximum attempts exceeded ({$attempts})";

        return new static($message, $queue, $jobId, $code, $previous);
    }
}
