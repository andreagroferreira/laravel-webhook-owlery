<?php

use WizardingCode\WebhookOwlery\Contracts\CircuitBreakerContract;
use WizardingCode\WebhookOwlery\Contracts\WebhookDispatcherContract;
use WizardingCode\WebhookOwlery\Contracts\WebhookReceiverContract;
use WizardingCode\WebhookOwlery\Contracts\WebhookRepositoryContract;
use WizardingCode\WebhookOwlery\Services\Owlery;
use WizardingCode\WebhookOwlery\Tests\TestCase;

uses(TestCase::class);

// Test creating an Owlery instance directly
it('can instantiate the Owlery service', function () {
    // Arrange
    $repository = $this->mock(WebhookRepositoryContract::class);
    $receiver = $this->mock(WebhookReceiverContract::class);
    $dispatcher = $this->mock(WebhookDispatcherContract::class);
    $circuitBreaker = $this->mock(CircuitBreakerContract::class);

    // Act
    $owlery = new Owlery(
        $repository,
        $receiver,
        $dispatcher,
        null,
        $circuitBreaker
    );

    // Assert
    expect($owlery)->toBeInstanceOf(Owlery::class);
});

// Test dispatching a webhook
it('can dispatch webhooks', function () {
    // Arrange
    $repository = $this->mock(WebhookRepositoryContract::class);
    $receiver = $this->mock(WebhookReceiverContract::class);
    $dispatcher = $this->mock(WebhookDispatcherContract::class);
    $circuitBreaker = $this->mock(CircuitBreakerContract::class);

    $event = 'test.event';
    $payload = ['key' => 'value'];

    $dispatcher->shouldReceive('dispatch')
        ->withArgs(function ($eventArg, $payloadArg, $subsArg = [], $metaArg = []) use ($event, $payload) {
            return $eventArg === $event && $payloadArg === $payload;
        })
        ->once()
        ->andReturn(true);

    // Act
    $owlery = new Owlery(
        $repository,
        $receiver,
        $dispatcher,
        null,
        $circuitBreaker
    );

    $result = $owlery->dispatcher()->dispatch($event, $payload);

    // Assert
    expect($result)->toBeTrue();
});

// Test specifying subscriptions for webhook dispatch
it('can specify subscriptions for webhook dispatch', function () {
    // Arrange
    $repository = $this->mock(WebhookRepositoryContract::class);
    $receiver = $this->mock(WebhookReceiverContract::class);
    $dispatcher = $this->mock(WebhookDispatcherContract::class);
    $circuitBreaker = $this->mock(CircuitBreakerContract::class);

    $event = 'test.event';
    $payload = ['key' => 'value'];
    $subscriptions = ['subscription-1', 'subscription-2'];

    $dispatcher->shouldReceive('dispatch')
        ->withArgs(function ($eventArg, $payloadArg, $subsArg = [], $metaArg = []) use ($event, $payload, $subscriptions) {
            return $eventArg === $event && $payloadArg === $payload && $subsArg === $subscriptions;
        })
        ->once()
        ->andReturn(true);

    // Act
    $owlery = new Owlery(
        $repository,
        $receiver,
        $dispatcher,
        null,
        $circuitBreaker
    );

    $result = $owlery->dispatcher()->dispatch($event, $payload, $subscriptions);

    // Assert
    expect($result)->toBeTrue();
});

// Test adding metadata to a webhook
it('can add metadata to a webhook', function () {
    // Arrange
    $repository = $this->mock(WebhookRepositoryContract::class);
    $receiver = $this->mock(WebhookReceiverContract::class);
    $dispatcher = $this->mock(WebhookDispatcherContract::class);
    $circuitBreaker = $this->mock(CircuitBreakerContract::class);

    $event = 'test.event';
    $payload = ['key' => 'value'];
    $metadata = ['source' => 'test'];

    $dispatcher->shouldReceive('dispatch')
        ->withArgs(function ($eventArg, $payloadArg, $subsArg = [], $metaArg = []) use ($event, $payload, $metadata) {
            return $eventArg === $event && $payloadArg === $payload && $metaArg === $metadata;
        })
        ->once()
        ->andReturn(true);

    // Act
    $owlery = new Owlery(
        $repository,
        $receiver,
        $dispatcher,
        null,
        $circuitBreaker
    );

    $result = $owlery->dispatcher()->dispatch($event, $payload, [], $metadata);

    // Assert
    expect($result)->toBeTrue();
});
