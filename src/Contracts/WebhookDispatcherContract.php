<?php

namespace WizardingCode\WebhookOwlery\Contracts;

use WizardingCode\WebhookOwlery\Models\WebhookDelivery;
use WizardingCode\WebhookOwlery\Models\WebhookEndpoint;

interface WebhookDispatcherContract
{
    /**
     * Send a webhook to a destination URL.
     *
     * @param string $url     The destination URL
     * @param string $event   The event type
     * @param array  $payload The webhook payload
     * @param array  $options Dispatch options (headers, authentication, etc.)
     */
    public function send(string $url, string $event, array $payload, array $options = []): WebhookDelivery;

    /**
     * Send a webhook to a configured endpoint.
     *
     * @param WebhookEndpoint|int|string $endpoint The endpoint model, ID, or UUID
     * @param string                     $event    The event type
     * @param array                      $payload  The webhook payload
     * @param array                      $options  Dispatch options
     */
    public function sendToEndpoint(WebhookEndpoint|int|string $endpoint, string $event, array $payload, array $options = []): WebhookDelivery;

    /**
     * Queue a webhook for later dispatch.
     *
     * @param string $url     The destination URL
     * @param string $event   The event type
     * @param array  $payload The webhook payload
     * @param array  $options Dispatch options
     */
    public function queue(string $url, string $event, array $payload, array $options = []): WebhookDelivery;

    /**
     * Queue a webhook to a configured endpoint for later dispatch.
     *
     * @param WebhookEndpoint|int|string $endpoint The endpoint model, ID, or UUID
     * @param string                     $event    The event type
     * @param array                      $payload  The webhook payload
     * @param array                      $options  Dispatch options
     */
    public function queueToEndpoint(WebhookEndpoint|int|string $endpoint, string $event, array $payload, array $options = []): WebhookDelivery;

    /**
     * Broadcast an event to all subscribed endpoints.
     *
     * @param string $event   The event type
     * @param array  $payload The webhook payload
     * @param array  $options Dispatch options
     *
     * @return array Array of WebhookDelivery instances
     */
    public function broadcast(string $event, array $payload, array $options = []): array;

    /**
     * Retry a previously failed webhook dispatch.
     *
     * @param WebhookDelivery|int|string $delivery The delivery model, ID, or UUID
     * @param array|null                 $options  Optional new options
     */
    public function retry(WebhookDelivery|int|string $delivery, ?array $options = null): WebhookDelivery;

    /**
     * Cancel a pending webhook dispatch.
     *
     * @param WebhookDelivery|int|string $delivery The delivery model, ID, or UUID
     * @param string|null                $reason   Optional reason for cancellation
     */
    public function cancel(WebhookDelivery|int|string $delivery, ?string $reason = null): WebhookDelivery;

    /**
     * Add a before dispatch hook.
     *
     * @param callable $callback The callback to execute before dispatch
     */
    public function beforeDispatch(callable $callback): self;

    /**
     * Add an after dispatch hook.
     *
     * @param callable $callback The callback to execute after dispatch
     */
    public function afterDispatch(callable $callback): self;
}
