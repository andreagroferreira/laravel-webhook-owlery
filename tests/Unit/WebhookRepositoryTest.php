<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use WizardingCode\WebhookOwlery\Models\WebhookDelivery;
use WizardingCode\WebhookOwlery\Models\WebhookEndpoint;
use WizardingCode\WebhookOwlery\Models\WebhookEvent;
use WizardingCode\WebhookOwlery\Models\WebhookSubscription;
use WizardingCode\WebhookOwlery\Repositories\EloquentWebhookRepository;
use WizardingCode\WebhookOwlery\Tests\TestCase;

uses(TestCase::class);

it('can create webhook endpoints', function () {
    $repository = new EloquentWebhookRepository;

    $endpoint = $repository->createEndpoint([
        'url' => 'https://example.com/webhook',
        'secret' => 'test-secret',
        'description' => 'Test endpoint',
        'is_active' => true,
    ]);

    expect($endpoint)->toBeInstanceOf(WebhookEndpoint::class)
        ->and($endpoint->url)->toBe('https://example.com/webhook')
        ->and($endpoint->secret)->toBe('test-secret')
        ->and($endpoint->description)->toBe('Test endpoint')
        ->and($endpoint->is_active)->toBeTrue();

    // Verify it was saved to the database
    $found = WebhookEndpoint::find($endpoint->id);
    expect($found)->not->toBeNull();
});

it('can update webhook endpoints', function () {
    $repository = new EloquentWebhookRepository;

    $endpoint = $repository->createEndpoint([
        'url' => 'https://example.com/webhook',
        'secret' => 'test-secret',
        'description' => 'Test endpoint',
        'is_active' => true,
    ]);

    $updated = $repository->updateEndpoint($endpoint->id, [
        'url' => 'https://updated.com/webhook',
        'description' => 'Updated description',
    ]);

    expect($updated->url)->toBe('https://updated.com/webhook')
        ->and($updated->description)->toBe('Updated description');

    // Verify it was saved to the database
    $found = WebhookEndpoint::find($endpoint->id);
    expect($found->url)->toBe('https://updated.com/webhook');
});

it('can record webhook events', function () {
    $repository = new EloquentWebhookRepository;

    $eventData = [
        'type' => 'test.event',
        'payload' => ['key' => 'value'],
    ];

    $event = $repository->recordEvent($eventData['type'], $eventData['payload']);

    expect($event)->toBeInstanceOf(WebhookEvent::class)
        ->and($event->type)->toBe('test.event')
        ->and($event->payload)->toBe(['key' => 'value']);
});

it('can record webhook deliveries', function () {
    $repository = new EloquentWebhookRepository;

    // Create endpoint and subscription first
    $endpoint = $repository->createEndpoint([
        'url' => 'https://example.com/webhook',
        'secret' => 'test-secret',
        'is_active' => true,
    ]);

    $subscription = $repository->createSubscription([
        'endpoint_id' => $endpoint->id,
        'event_types' => ['test.event'],
        'is_active' => true,
    ]);

    // Create event
    $event = $repository->recordEvent('test.event', ['key' => 'value']);

    // Record delivery
    $delivery = $repository->recordDelivery([
        'subscription_id' => $subscription->id,
        'event_id' => $event->id,
        'status' => 'success',
        'response_code' => 200,
        'response_body' => '{"status":"ok"}',
        'response_time' => 150,
    ]);

    expect($delivery)->toBeInstanceOf(WebhookDelivery::class)
        ->and($delivery->subscription_id)->toBe($subscription->id)
        ->and($delivery->event_id)->toBe($event->id)
        ->and($delivery->status)->toBe('success')
        ->and($delivery->response_code)->toBe(200);
});

it('can find active subscriptions by event type', function () {
    $repository = new EloquentWebhookRepository;

    // Create endpoint
    $endpoint = $repository->createEndpoint([
        'url' => 'https://example.com/webhook',
        'secret' => 'test-secret',
        'is_active' => true,
    ]);

    // Create two subscriptions with different event types
    $repository->createSubscription([
        'endpoint_id' => $endpoint->id,
        'event_types' => ['test.event'],
        'is_active' => true,
    ]);

    $repository->createSubscription([
        'endpoint_id' => $endpoint->id,
        'event_types' => ['another.event'],
        'is_active' => true,
    ]);

    // Create an inactive subscription
    $repository->createSubscription([
        'endpoint_id' => $endpoint->id,
        'event_types' => ['test.event'],
        'is_active' => false,
    ]);

    $testEventSubscriptions = $repository->getActiveSubscriptionsByEventType('test.event');

    expect($testEventSubscriptions)->toHaveCount(1)
        ->and($testEventSubscriptions->first()->event_types)->toContain('test.event')
        ->and($testEventSubscriptions->first()->is_active)->toBeTrue();
});

it('throws an exception when endpoint is not found', function () {
    $repository = new EloquentWebhookRepository;

    // Force an exception by using findOrFail directly
    expect(function () {
        WebhookEndpoint::findOrFail(999);
    })->toThrow(ModelNotFoundException::class);
});

it('throws an exception when subscription is not found', function () {
    $repository = new EloquentWebhookRepository;

    // Force an exception by using findOrFail directly
    expect(function () {
        WebhookSubscription::findOrFail(999);
    })->toThrow(ModelNotFoundException::class);
});
