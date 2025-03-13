<?php

namespace WizardingCode\WebhookOwlery\Contracts;

use Closure;
use Illuminate\Http\Request;

interface WebhookReceiverContract
{
    /**
     * Configure a webhook endpoint for receiving webhooks.
     *
     * @param string $source   The source/provider name (e.g., 'stripe', 'github')
     * @param string $endpoint The endpoint path
     * @param array  $options  Configuration options
     */
    public function configureEndpoint(string $source, string $endpoint, array $options = []): mixed;

    /**
     * Handle an incoming webhook request.
     *
     * @param string  $source  The source/provider name
     * @param Request $request The HTTP request
     */
    public function handleRequest(string $source, Request $request): mixed;

    /**
     * Register a handler for a specific webhook event.
     *
     * @param string           $source  The source/provider name
     * @param string           $event   The event name or pattern
     * @param callable|Closure $handler The handler function
     */
    public function on(string $source, string $event, callable|Closure $handler): mixed;

    /**
     * Register a handler for all events from a source.
     *
     * @param string           $source  The source/provider name
     * @param callable|Closure $handler The handler function
     */
    public function onAny(string $source, callable|Closure $handler): mixed;

    /**
     * Register a handler that runs before webhook processing.
     *
     * @param string           $source  The source/provider name
     * @param callable|Closure $handler The handler function
     */
    public function before(string $source, callable|Closure $handler): mixed;

    /**
     * Register a handler that runs after webhook processing.
     *
     * @param string           $source  The source/provider name
     * @param callable|Closure $handler The handler function
     */
    public function after(string $source, callable|Closure $handler): mixed;

    /**
     * Verify if a webhook is valid based on its signature.
     *
     * @param string  $source  The source/provider name
     * @param Request $request The HTTP request
     */
    public function verifySignature(string $source, Request $request): bool;

    /**
     * Get a registered handler for a specific event.
     *
     * @param string $source The source/provider name
     * @param string $event  The event name
     */
    public function getHandler(string $source, string $event): ?callable;
}
