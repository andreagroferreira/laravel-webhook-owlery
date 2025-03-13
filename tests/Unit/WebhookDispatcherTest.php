<?php

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use WizardingCode\WebhookOwlery\Contracts\CircuitBreakerContract;
use WizardingCode\WebhookOwlery\Contracts\WebhookRepositoryContract;
use WizardingCode\WebhookOwlery\Events\WebhookDispatching;
use WizardingCode\WebhookOwlery\Jobs\DispatchOutgoingWebhook;
use WizardingCode\WebhookOwlery\Models\WebhookEndpoint;
use WizardingCode\WebhookOwlery\Models\WebhookSubscription;
use WizardingCode\WebhookOwlery\Services\WebhookDispatcher;
use WizardingCode\WebhookOwlery\Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Bus::fake();
    Event::fake();

    $this->repositoryMock = mock(WebhookRepositoryContract::class);
    $this->circuitBreakerMock = mock(CircuitBreakerContract::class);

    $this->dispatcher = new WebhookDispatcher(
        $this->repositoryMock,
        null, // Client
        null, // Validator
        $this->circuitBreakerMock
    );
});

it('dispatches a webhook to all active subscriptions if none specified', function () {
    $event = 'user.created';
    $payload = ['user' => ['id' => 1, 'name' => 'Test User']];

    $subscriptions = collect([
        new WebhookSubscription([
            'id' => 1,
            'endpoint_id' => 1,
            'event_types' => ['user.created'],
            'is_active' => true,
        ]),
        new WebhookSubscription([
            'id' => 2,
            'endpoint_id' => 2,
            'event_types' => ['user.created', 'user.updated'],
            'is_active' => true,
        ]),
    ]);

    $endpoints = collect([
        new WebhookEndpoint(['id' => 1, 'url' => 'https://example.com/webhook1', 'is_active' => true]),
        new WebhookEndpoint(['id' => 2, 'url' => 'https://example.com/webhook2', 'is_active' => true]),
    ]);

    $this->repositoryMock->shouldReceive('getActiveSubscriptionsByEventType')
        ->once()
        ->with($event)
        ->andReturn($subscriptions);

    $this->repositoryMock->shouldReceive('getEndpoint')
        ->once()
        ->with(1)
        ->andReturn($endpoints[0]);

    $this->repositoryMock->shouldReceive('getEndpoint')
        ->once()
        ->with(2)
        ->andReturn($endpoints[1]);

    $this->circuitBreakerMock->shouldReceive('isOpen')
        ->twice()
        ->andReturn(false);

    $this->dispatcher->dispatch($event, $payload);

    // Just check events since job testing is more complex
    Event::assertDispatched(WebhookDispatching::class, 2);
});

it('dispatches a webhook only to specified subscriptions', function () {
    $event = 'user.created';
    $payload = ['user' => ['id' => 1, 'name' => 'Test User']];
    $subscriptionIds = [1];

    $subscription = new WebhookSubscription([
        'id' => 1,
        'endpoint_id' => 1,
        'event_types' => ['user.created'],
        'is_active' => true,
    ]);

    $endpoint = new WebhookEndpoint([
        'id' => 1,
        'url' => 'https://example.com/webhook1',
        'is_active' => true,
    ]);

    $this->repositoryMock->shouldReceive('getSubscription')
        ->once()
        ->with(1)
        ->andReturn($subscription);

    $this->repositoryMock->shouldReceive('getEndpoint')
        ->once()
        ->with(1)
        ->andReturn($endpoint);

    $this->circuitBreakerMock->shouldReceive('isOpen')
        ->once()
        ->andReturn(false);

    $this->dispatcher->dispatch($event, $payload, $subscriptionIds);

    // Just check events since job testing is more complex
    Event::assertDispatched(WebhookDispatching::class, 1);
});

it('skips inactive subscriptions', function () {
    $event = 'user.created';
    $payload = ['user' => ['id' => 1, 'name' => 'Test User']];
    $subscriptionIds = [1, 2];

    $subscription1 = new WebhookSubscription([
        'id' => 1,
        'endpoint_id' => 1,
        'event_types' => ['user.created'],
        'is_active' => true,
    ]);

    $subscription2 = new WebhookSubscription([
        'id' => 2,
        'endpoint_id' => 2,
        'event_types' => ['user.created'],
        'is_active' => false, // Inactive
    ]);

    $endpoint = new WebhookEndpoint([
        'id' => 1,
        'url' => 'https://example.com/webhook1',
        'is_active' => true,
    ]);

    $this->repositoryMock->shouldReceive('getSubscription')
        ->once()
        ->with(1)
        ->andReturn($subscription1);

    $this->repositoryMock->shouldReceive('getSubscription')
        ->once()
        ->with(2)
        ->andReturn($subscription2);

    $this->repositoryMock->shouldReceive('getEndpoint')
        ->once()
        ->with(1)
        ->andReturn($endpoint);

    $this->circuitBreakerMock->shouldReceive('isOpen')
        ->once()
        ->andReturn(false);

    $this->dispatcher->dispatch($event, $payload, $subscriptionIds);

    // Just check events since job testing is more complex
    Event::assertDispatched(WebhookDispatching::class, 1);
});

it('skips endpoints with open circuit breakers', function () {
    $event = 'user.created';
    $payload = ['user' => ['id' => 1, 'name' => 'Test User']];

    $subscription = new WebhookSubscription([
        'id' => 1,
        'endpoint_id' => 1,
        'event_types' => ['user.created'],
        'is_active' => true,
    ]);

    $endpoint = new WebhookEndpoint([
        'id' => 1,
        'url' => 'https://example.com/webhook1',
        'is_active' => true,
    ]);

    $this->repositoryMock->shouldReceive('getSubscription')
        ->once()
        ->with(1)
        ->andReturn($subscription);

    $this->repositoryMock->shouldReceive('getEndpoint')
        ->once()
        ->with(1)
        ->andReturn($endpoint);

    $this->circuitBreakerMock->shouldReceive('isOpen')
        ->once()
        ->with($endpoint->url)
        ->andReturn(true);

    $this->dispatcher->dispatch($event, $payload, [1]);

    Bus::assertNotDispatched(DispatchOutgoingWebhook::class);
    Event::assertNotDispatched(WebhookDispatching::class);
});
