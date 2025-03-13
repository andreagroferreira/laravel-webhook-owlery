<?php

namespace WizardingCode\WebhookOwlery\Contracts;

use WizardingCode\WebhookOwlery\Exceptions\CircuitOpenException;

interface CircuitBreakerContract
{
    /**
     * States for the circuit breaker.
     */
    public const STATE_CLOSED = 'closed';   // Normal operation, requests allowed
    public const STATE_OPEN = 'open';       // Circuit tripped, requests blocked
    public const STATE_HALF_OPEN = 'half_open'; // Trial period, allowing test requests

    /**
     * Check if the circuit is open (failing) for a destination.
     *
     * @param string $destination The destination identifier
     */
    public function isOpen(string $destination): bool;

    /**
     * Check if the circuit is closed (normal operation) for a destination.
     *
     * @param string $destination The destination identifier
     */
    public function isClosed(string $destination): bool;

    /**
     * Check if the circuit is in half-open state (trial period) for a destination.
     *
     * @param string $destination The destination identifier
     */
    public function isHalfOpen(string $destination): bool;

    /**
     * Get the current state of the circuit for a destination.
     *
     * @param string $destination The destination identifier
     *
     * @return string One of the STATE_* constants
     */
    public function getState(string $destination): string;

    /**
     * Record a successful call to a destination.
     *
     * @param string $destination The destination identifier
     */
    public function recordSuccess(string $destination): void;

    /**
     * Record a failed call to a destination.
     *
     * @param string $destination The destination identifier
     */
    public function recordFailure(string $destination): void;

    /**
     * Force the circuit to open for a destination.
     *
     * @param string   $destination The destination identifier
     * @param int|null $duration    Duration in seconds (null = use config)
     */
    public function forceOpen(string $destination, ?int $duration = null): void;

    /**
     * Force the circuit to close for a destination.
     *
     * @param string $destination The destination identifier
     */
    public function forceClose(string $destination): void;

    /**
     * Reset the circuit stats for a destination.
     *
     * @param string $destination The destination identifier
     */
    public function reset(string $destination): void;

    /**
     * Get the failure count for a destination.
     *
     * @param string $destination The destination identifier
     */
    public function getFailureCount(string $destination): int;

    /**
     * Get the success count for a destination.
     *
     * @param string $destination The destination identifier
     */
    public function getSuccessCount(string $destination): int;

    /**
     * Get the timestamp when the circuit will transition from open to half-open.
     *
     * @param string $destination The destination identifier
     *
     * @return int|null Unix timestamp or null if not applicable
     */
    public function getResetTimeout(string $destination): ?int;

    /**
     * Execute a callable with circuit breaker protection.
     *
     * @param string        $destination The destination identifier
     * @param callable      $callable    The callable to execute
     * @param callable|null $fallback    Optional fallback to execute if circuit is open
     *
     * @throws CircuitOpenException When circuit is open and no fallback provided
     */
    public function execute(string $destination, callable $callable, ?callable $fallback = null): mixed;
}
