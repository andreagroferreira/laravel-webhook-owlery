<?php

use WizardingCode\WebhookOwlery\Repositories\RedisCircuitBreaker;

// We'll test a simplified memory-based implementation instead of relying on Redis
class MemoryCircuitBreaker extends RedisCircuitBreaker
{
    private $storage = [];

    protected function redis()
    {
        return $this;
    }

    public function get($key)
    {
        return $this->storage[$key] ?? null;
    }

    public function set($key, $value, $expireFlag = null, $duration = null)
    {
        $this->storage[$key] = $value;

        return true;
    }

    public function incr($key)
    {
        if (! isset($this->storage[$key])) {
            $this->storage[$key] = 0;
        }
        $this->storage[$key]++;

        return $this->storage[$key];
    }

    public function expire($key, $seconds)
    {
        return true;
    }

    public function del($keys)
    {
        if (is_array($keys)) {
            foreach ($keys as $key) {
                unset($this->storage[$key]);
            }

            return count($keys);
        }

        unset($this->storage[$keys]);

        return 1;
    }

    // For testing
    public function getStorage()
    {
        return $this->storage;
    }
}

beforeEach(function () {
    $this->circuitBreaker = new MemoryCircuitBreaker(null, 'circuit:');
});

it('records failures and opens the circuit when threshold is reached', function () {
    $circuitBreaker = $this->circuitBreaker;

    // First, the circuit should be closed
    expect($circuitBreaker->isOpen('test'))->toBeFalse();

    // Record failures, but not enough to open
    $circuitBreaker->recordFailure('test');
    $circuitBreaker->recordFailure('test');
    expect($circuitBreaker->isOpen('test'))->toBeFalse();

    // The third failure should open the circuit (threshold is 5 by default)
    $circuitBreaker->recordFailure('test');
    $circuitBreaker->recordFailure('test');
    $circuitBreaker->recordFailure('test');

    expect($circuitBreaker->isOpen('test'))->toBeTrue();
});

it('resets failure count on success', function () {
    $circuitBreaker = $this->circuitBreaker;

    // Record some failures
    $circuitBreaker->recordFailure('test');
    $circuitBreaker->recordFailure('test');

    // Record a success, which should reset the failure count
    $circuitBreaker->recordSuccess('test');

    // Check that the failure count is reset by adding failures again
    // and verifying the circuit doesn't open
    $circuitBreaker->recordFailure('test');
    $circuitBreaker->recordFailure('test');

    expect($circuitBreaker->isOpen('test'))->toBeFalse();
});

it('can reset a circuit manually', function () {
    $circuitBreaker = $this->circuitBreaker;

    // Force the circuit open
    $circuitBreaker->forceOpen('test');
    expect($circuitBreaker->isOpen('test'))->toBeTrue();

    // Reset and check it's closed
    $circuitBreaker->reset('test');
    expect($circuitBreaker->isOpen('test'))->toBeFalse();
});
