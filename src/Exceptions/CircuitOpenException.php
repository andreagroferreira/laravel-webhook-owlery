<?php

namespace WizardingCode\WebhookOwlery\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

class CircuitOpenException extends OwleryException
{
    protected string $destination;

    protected ?int $failureCount;

    protected ?int $reopenAt;

    /**
     * Create a new circuit open exception instance.
     *
     * @return void
     */
    public function __construct(
        string $destination,
        ?int $failureCount = null,
        ?int $reopenAt = null,
        string $message = 'Circuit breaker is open',
        int $code = 503,
        ?Throwable $previous = null
    ) {
        $this->destination = $destination;
        $this->failureCount = $failureCount;
        $this->reopenAt = $reopenAt;

        $fullMessage = "{$message} for destination '{$destination}'";

        if ($failureCount !== null) {
            $fullMessage .= " after {$failureCount} failures";
        }

        if ($reopenAt !== null) {
            $secondsRemaining = max(0, $reopenAt - time());
            $fullMessage .= ", will retry in {$secondsRemaining} seconds";
        }

        parent::__construct($fullMessage, $code, $previous);
    }

    /**
     * Get the destination that has an open circuit.
     */
    final public function getDestination(): string
    {
        return $this->destination;
    }

    /**
     * Get the failure count.
     */
    final public function getFailureCount(): ?int
    {
        return $this->failureCount;
    }

    /**
     * Get the timestamp when the circuit will be half-open again.
     *
     * @return int|null Unix timestamp
     */
    final public function getReopenAt(): ?int
    {
        return $this->reopenAt;
    }

    /**
     * Calculate how many seconds until the circuit will be half-open again.
     *
     * @return int|null Seconds, or null if no reopen time is set
     */
    final public function getSecondsUntilRetry(): ?int
    {
        if ($this->reopenAt === null) {
            return null;
        }

        return max(0, $this->reopenAt - time());
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(Request $request): Response|JsonResponse
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            $response = [
                'message' => $this->getMessage(),
                'error' => 'circuit_open',
                'destination' => $this->destination,
                'status' => 'error',
                'code' => $this->getCode(),
            ];

            if ($this->reopenAt !== null) {
                $response['retry_after'] = $this->getSecondsUntilRetry();
                $response['reopen_at'] = $this->reopenAt;
            }

            return response()->json($response, $this->getCode());
        }

        return response($this->getMessage(), $this->getCode());
    }
}
