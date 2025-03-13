<?php

namespace WizardingCode\WebhookOwlery\Facades;

use Illuminate\Support\Facades\Facade;
use WizardingCode\WebhookOwlery\Contracts\CircuitBreakerContract;

/**
 * @method static bool isOpen(string $destination)
 * @method static bool isClosed(string $destination)
 * @method static bool isHalfOpen(string $destination)
 * @method static string getState(string $destination)
 * @method static void recordSuccess(string $destination)
 * @method static void recordFailure(string $destination)
 * @method static void forceOpen(string $destination, ?int $duration = null)
 * @method static void forceClose(string $destination)
 * @method static void reset(string $destination)
 * @method static int getFailureCount(string $destination)
 * @method static int getSuccessCount(string $destination)
 * @method static int|null getResetTimeout(string $destination)
 * @method static mixed execute(string $destination, callable $callable, callable $fallback = null)
 *
 * @see CircuitBreakerContract
 */
class CircuitBreaker extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return CircuitBreakerContract::class;
    }
}
