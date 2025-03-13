<?php

namespace WizardingCode\WebhookOwlery\Repositories;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;
use Throwable;
use WizardingCode\WebhookOwlery\Contracts\CircuitBreakerContract;
use WizardingCode\WebhookOwlery\Exceptions\CircuitOpenException;

class RedisCircuitBreaker implements CircuitBreakerContract
{
    /**
     * The Redis connection to use.
     */
    private ?string $connection;

    /**
     * The prefix for all Redis keys.
     */
    private string $keyPrefix;

    /**
     * The number of failures before the circuit opens.
     */
    private int $threshold;

    /**
     * The number of seconds the circuit remains open.
     */
    private int $openDuration;

    /**
     * Create a new Redis circuit breaker instance.
     *
     * @param string|null $connection   Redis connection name
     * @param string      $keyPrefix    Prefix for Redis keys
     * @param int|null    $threshold    Failure threshold to trip circuit
     * @param int|null    $recoveryTime Time in seconds circuit stays open
     *
     * @return void
     */
    public function __construct(
        ?string $connection = null,
        string $keyPrefix = 'webhook_circuit:',
        ?int $threshold = null,
        ?int $recoveryTime = null
    ) {
        $this->connection = $connection;
        $this->keyPrefix = $keyPrefix;

        // Always use the provided values or fall back to defaults
        $this->threshold = $threshold ?? 5;
        $this->openDuration = $recoveryTime ?? 60;
    }

    /**
     * Get the Redis connection.
     */
    protected function redis()
    {
        // All environments use the same code path now
        return Redis::connection($this->connection);
    }

    /**
     * Check if the circuit is open (failing) for a destination.
     *
     * @param string $destination The destination identifier
     */
    final public function isOpen(string $destination): bool
    {
        return $this->getState($destination) === self::STATE_OPEN;
    }

    /**
     * Check if the circuit is closed (normal operation) for a destination.
     *
     * @param string $destination The destination identifier
     */
    final public function isClosed(string $destination): bool
    {
        return $this->getState($destination) === self::STATE_CLOSED;
    }

    /**
     * Check if the circuit is in half-open state (trial period) for a destination.
     *
     * @param string $destination The destination identifier
     */
    final public function isHalfOpen(string $destination): bool
    {
        return $this->getState($destination) === self::STATE_HALF_OPEN;
    }

    /**
     * Get the current state of the circuit for a destination.
     *
     * @param string $destination The destination identifier
     *
     * @return string One of the STATE_* constants
     */
    final public function getState(string $destination): string
    {
        $redis = $this->redis();

        // Check if circuit is explicitly open
        $openUntil = $redis->get($this->getOpenKey($destination));
        if ($openUntil !== null) {
            $openUntil = (int) $openUntil;

            // If the open period has expired, transition to half-open
            if ($openUntil <= time()) {
                $redis->set($this->getStateKey($destination), self::STATE_HALF_OPEN);
                $redis->del($this->getOpenKey($destination));

                return self::STATE_HALF_OPEN;
            }

            return self::STATE_OPEN;
        }

        // Check for explicit state
        $state = $redis->get($this->getStateKey($destination));
        if ($state !== null) {
            return $state;
        }

        // Default to closed
        return self::STATE_CLOSED;
    }

    /**
     * Record a successful call to a destination.
     *
     * @param string $destination The destination identifier
     */
    final public function recordSuccess(string $destination): void
    {
        $redis = $this->redis();
        $state = $this->getState($destination);

        // Increment success counter
        $redis->incr($this->getSuccessKey($destination));

        // If half-open and success, transition to closed
        if ($state === self::STATE_HALF_OPEN) {
            $redis->set($this->getStateKey($destination), self::STATE_CLOSED);
            $redis->del($this->getOpenKey($destination));
        }

        // Reset failure counter on success
        $redis->set($this->getFailureKey($destination), 0);
    }

    /**
     * Record a failed call to a destination.
     *
     * @param string $destination The destination identifier
     */
    final public function recordFailure(string $destination): void
    {
        $redis = $this->redis();
        $state = $this->getState($destination);

        // Increment failure counter
        $failures = $redis->incr($this->getFailureKey($destination));

        // If already half-open and failed, open the circuit
        if ($state === self::STATE_HALF_OPEN) {
            $this->openCircuit($destination);

            return;
        }

        // If closed and failures exceed threshold, open the circuit
        if ($state === self::STATE_CLOSED && $failures >= $this->threshold) {
            $this->openCircuit($destination);
        }
    }

    /**
     * Force the circuit to open for a destination.
     *
     * @param string   $destination The destination identifier
     * @param int|null $duration    Duration in seconds (null = use config)
     */
    final public function forceOpen(string $destination, ?int $duration = null): void
    {
        $this->openCircuit($destination, $duration);
    }

    /**
     * Force the circuit to close for a destination.
     *
     * @param string $destination The destination identifier
     */
    final public function forceClose(string $destination): void
    {
        $redis = $this->redis();

        $redis->set($this->getStateKey($destination), self::STATE_CLOSED);
        $redis->del($this->getOpenKey($destination));
        $redis->set($this->getFailureKey($destination), 0);
    }

    /**
     * Reset the circuit stats for a destination.
     *
     * @param string $destination The destination identifier
     */
    final public function reset(string $destination): void
    {
        $redis = $this->redis();

        $redis->del([
            $this->getStateKey($destination),
            $this->getOpenKey($destination),
            $this->getFailureKey($destination),
            $this->getSuccessKey($destination),
        ]);
    }

    /**
     * Get the failure count for a destination.
     *
     * @param string $destination The destination identifier
     */
    final public function getFailureCount(string $destination): int
    {
        $failures = $this->redis()->get($this->getFailureKey($destination));

        return $failures !== null ? (int) $failures : 0;
    }

    /**
     * Get the success count for a destination.
     *
     * @param string $destination The destination identifier
     */
    final public function getSuccessCount(string $destination): int
    {
        $successes = $this->redis()->get($this->getSuccessKey($destination));

        return $successes !== null ? (int) $successes : 0;
    }

    /**
     * Get the timestamp when the circuit will transition from open to half-open.
     *
     * @param string $destination The destination identifier
     *
     * @return int|null Unix timestamp or null if not applicable
     */
    final public function getResetTimeout(string $destination): ?int
    {
        $openUntil = $this->redis()->get($this->getOpenKey($destination));

        return $openUntil !== null ? (int) $openUntil : null;
    }

    /**
     * Execute a callable with circuit breaker protection.
     *
     * @param string        $destination The destination identifier
     * @param callable      $callable    The callable to execute
     * @param callable|null $fallback    Optional fallback to execute if circuit is open
     *
     * @throws \WizardingCode\WebhookOwlery\Exceptions\CircuitOpenException|Throwable When circuit is open and no fallback provided
     */
    final public function execute(string $destination, callable $callable, ?callable $fallback = null): mixed
    {
        // Check if circuit is open
        if ($this->isOpen($destination)) {
            if ($fallback !== null) {
                return $fallback();
            }

            throw new CircuitOpenException(
                $destination,
                $this->getFailureCount($destination),
                $this->getResetTimeout($destination)
            );
        }

        try {
            // Execute the callable
            $result = $callable();

            // Record success
            $this->recordSuccess($destination);

            return $result;
        } catch (Throwable $e) {
            // Record failure
            $this->recordFailure($destination);

            // If circuit is now open and we have a fallback, use it
            if ($this->isOpen($destination) && $fallback !== null) {
                return $fallback();
            }

            throw $e;
        }
    }

    /**
     * Open the circuit for a destination.
     *
     * @param string   $destination The destination identifier
     * @param int|null $duration    Duration in seconds (null = use config)
     */
    final protected function openCircuit(string $destination, ?int $duration = null): void
    {
        $duration = $duration ?? $this->openDuration;
        $openUntil = time() + $duration;

        $redis = $this->redis();
        $redis->set($this->getStateKey($destination), self::STATE_OPEN);
        $redis->set($this->getOpenKey($destination), $openUntil);
    }

    /**
     * Get the key for the circuit state.
     *
     * @param string $destination The destination identifier
     */
    private function getStateKey(string $destination): string
    {
        return $this->keyPrefix . $destination . ':state';
    }

    /**
     * Get the key for the circuit open until timestamp.
     *
     * @param string $destination The destination identifier
     */
    private function getOpenKey(string $destination): string
    {
        return $this->keyPrefix . $destination . ':open_until';
    }

    /**
     * Get the key for the failure counter.
     *
     * @param string $destination The destination identifier
     */
    private function getFailureKey(string $destination): string
    {
        return $this->keyPrefix . $destination . ':failures';
    }

    /**
     * Get the key for the success counter.
     *
     * @param string $destination The destination identifier
     */
    private function getSuccessKey(string $destination): string
    {
        return $this->keyPrefix . $destination . ':successes';
    }
}
