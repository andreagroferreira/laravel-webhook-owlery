<?php

namespace WizardingCode\WebhookOwlery\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use WizardingCode\WebhookOwlery\Contracts\WebhookReceiverContract;
use WizardingCode\WebhookOwlery\Facades\Owlery;
use WizardingCode\WebhookOwlery\Http\Requests\SendWebhookRequest;
use WizardingCode\WebhookOwlery\Http\Requests\WebhookEndpointRequest;
use WizardingCode\WebhookOwlery\Http\Requests\WebhookReceiveRequest;

class WebhookController extends Controller
{
    /**
     * Handle an incoming webhook request from any provider.
     *
     * @param string                  $source   The source provider
     * @param WebhookReceiveRequest   $request  The request object
     * @param WebhookReceiverContract $receiver The webhook receiver service
     */
    final public function handle(string $source, WebhookReceiveRequest $request, WebhookReceiverContract $receiver): JsonResponse
    {
        try {
            // Check if the webhook request is valid
            if (! $request->isValidWebhook()) {
                Log::warning('Invalid webhook request received', [
                    'source' => $source,
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'message' => 'Invalid webhook request',
                    'success' => false,
                ], 400);
            }

            // Process the webhook through the receiver service
            // Return the response from the receiver
            return $receiver->handleRequest($source, $request);
        } catch (\Throwable $e) {
            Log::error('Error handling webhook', [
                'source' => $source,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error processing webhook',
                'success' => false,
            ], 500);
        }
    }

    /**
     * Handle provider-specific webhooks with specialized implementations.
     *
     * @param string  $provider The provider name (stripe, github, etc)
     * @param Request $request  The request object
     */
    final public function handleProvider(string $provider, Request $request): JsonResponse
    {
        // Map provider names to their request classes
        $providerRequestMap = [
            'stripe' => \WizardingCode\WebhookOwlery\Http\Requests\Providers\StripeWebhookRequest::class,
            'github' => \WizardingCode\WebhookOwlery\Http\Requests\Providers\GitHubWebhookRequest::class,
        ];

        // Check if we have a specific handler for this provider
        if (isset($providerRequestMap[$provider]) && class_exists($providerRequestMap[$provider])) {
            // Create a new instance of the provider-specific request
            $requestClass = $providerRequestMap[$provider];
            $providerRequest = $requestClass::createFrom($request);

            // Forward to generic handler with the specialized request
            return $this->handle($provider, $providerRequest, app(WebhookReceiverContract::class));
        }

        // If no specific handler, use generic one
        return $this->handle($provider, new WebhookReceiveRequest($request), app(WebhookReceiverContract::class));
    }

    /**
     * List all webhook endpoints.
     */
    final public function listEndpoints(Request $request): JsonResponse
    {
        try {
            $filters = [];

            // Extract filters from query params
            if ($request->has('active')) {
                $filters['is_active'] = filter_var($request->query('active'), FILTER_VALIDATE_BOOLEAN);
            }

            if ($request->has('source')) {
                $filters['source'] = $request->query('source');
            }

            // Get endpoints from the repository
            $endpoints = Owlery::repository()->findEndpoints($filters);

            // Transform the endpoints to remove sensitive data
            $result = $endpoints->map(function ($endpoint) {
                return [
                    'id' => $endpoint->id,
                    'uuid' => $endpoint->uuid,
                    'name' => $endpoint->name,
                    'url' => $endpoint->url,
                    'description' => $endpoint->description,
                    'is_active' => $endpoint->is_active,
                    'source' => $endpoint->source,
                    'events' => $endpoint->events,
                    'created_at' => $endpoint->created_at->toIso8601String(),
                    'updated_at' => $endpoint->updated_at->toIso8601String(),
                ];
            });

            return response()->json([
                'message' => 'Webhook endpoints retrieved successfully',
                'success' => true,
                'data' => [
                    'endpoints' => $result,
                    'count' => $result->count(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error retrieving webhook endpoints', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error retrieving webhook endpoints',
                'success' => false,
            ], 500);
        }
    }

    /**
     * Get a single webhook endpoint.
     *
     * @param string $id Endpoint ID or UUID
     */
    final public function getEndpoint(string $id): JsonResponse
    {
        try {
            $endpoint = Owlery::repository()->getEndpoint($id);

            if (! $endpoint) {
                return response()->json([
                    'message' => 'Webhook endpoint not found',
                    'success' => false,
                ], 404);
            }

            // Transform the endpoint to remove sensitive data
            $result = [
                'id' => $endpoint->id,
                'uuid' => $endpoint->uuid,
                'name' => $endpoint->name,
                'url' => $endpoint->url,
                'description' => $endpoint->description,
                'is_active' => $endpoint->is_active,
                'source' => $endpoint->source,
                'events' => $endpoint->events,
                'timeout' => $endpoint->timeout,
                'retry_limit' => $endpoint->retry_limit,
                'retry_interval' => $endpoint->retry_interval,
                'headers' => $endpoint->headers,
                'created_at' => $endpoint->created_at->toIso8601String(),
                'updated_at' => $endpoint->updated_at->toIso8601String(),
                // Don't include the secret and signature_algorithm
            ];

            return response()->json([
                'message' => 'Webhook endpoint retrieved successfully',
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error retrieving webhook endpoint', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error retrieving webhook endpoint',
                'success' => false,
            ], 500);
        }
    }

    /**
     * Create a new webhook endpoint.
     */
    public function createEndpoint(WebhookEndpointRequest $request): JsonResponse
    {
        try {
            // Create the endpoint
            $endpoint = Owlery::createEndpoint(
                $request->input('name'),
                $request->input('url'),
                $request->input('events', []),
                $request->except(['name', 'url', 'events'])
            );

            // Transform the endpoint to remove sensitive data
            $result = [
                'id' => $endpoint->id,
                'uuid' => $endpoint->uuid,
                'name' => $endpoint->name,
                'url' => $endpoint->url,
                'description' => $endpoint->description,
                'is_active' => $endpoint->is_active,
                'source' => $endpoint->source,
                'events' => $endpoint->events,
                'created_at' => $endpoint->created_at->toIso8601String(),
                'updated_at' => $endpoint->updated_at->toIso8601String(),
            ];

            return response()->json([
                'message' => 'Webhook endpoint created successfully',
                'success' => true,
                'data' => $result,
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Error creating webhook endpoint', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error creating webhook endpoint: ' . $e->getMessage(),
                'success' => false,
            ], 500);
        }
    }

    /**
     * Update a webhook endpoint.
     *
     * @param string $id Endpoint ID or UUID
     */
    public function updateEndpoint(string $id, WebhookEndpointRequest $request): JsonResponse
    {
        try {
            // Get the endpoint
            $endpoint = Owlery::repository()->getEndpoint($id);

            if (! $endpoint) {
                return response()->json([
                    'message' => 'Webhook endpoint not found',
                    'success' => false,
                ], 404);
            }

            // Update the endpoint
            foreach ($request->validated() as $key => $value) {
                if ($request->has($key)) {
                    $endpoint->{$key} = $value;
                }
            }

            $endpoint->save();

            // Transform the endpoint to remove sensitive data
            $result = [
                'id' => $endpoint->id,
                'uuid' => $endpoint->uuid,
                'name' => $endpoint->name,
                'url' => $endpoint->url,
                'description' => $endpoint->description,
                'is_active' => $endpoint->is_active,
                'source' => $endpoint->source,
                'events' => $endpoint->events,
                'updated_at' => $endpoint->updated_at->toIso8601String(),
            ];

            return response()->json([
                'message' => 'Webhook endpoint updated successfully',
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error updating webhook endpoint', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error updating webhook endpoint: ' . $e->getMessage(),
                'success' => false,
            ], 500);
        }
    }

    /**
     * Delete a webhook endpoint.
     *
     * @param string $id Endpoint ID or UUID
     */
    final public function deleteEndpoint(string $id): JsonResponse
    {
        try {
            // Get the endpoint
            $endpoint = Owlery::repository()->getEndpoint($id);

            if (! $endpoint) {
                return response()->json([
                    'message' => 'Webhook endpoint not found',
                    'success' => false,
                ], 404);
            }

            // Delete the endpoint
            $endpoint->delete();

            return response()->json([
                'message' => 'Webhook endpoint deleted successfully',
                'success' => true,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error deleting webhook endpoint', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error deleting webhook endpoint: ' . $e->getMessage(),
                'success' => false,
            ], 500);
        }
    }

    /**
     * Send a webhook.
     */
    final public function sendWebhook(SendWebhookRequest $request): JsonResponse
    {
        try {
            // Determine if we should use URL or endpoint ID
            if ($request->has('endpoint_id')) {
                $endpointId = $request->input('endpoint_id');
                $delivery = Owlery::dispatcher()->sendToEndpoint(
                    $endpointId,
                    $request->input('event'),
                    $request->input('payload'),
                    $request->input('options', [])
                );
            } else {
                $url = $request->input('url');
                $delivery = Owlery::dispatcher()->send(
                    $url,
                    $request->input('event'),
                    $request->input('payload'),
                    $request->input('options', [])
                );
            }

            // Transform the delivery to return
            $result = [
                'id' => $delivery->id,
                'uuid' => $delivery->uuid,
                'destination' => $delivery->destination,
                'event' => $delivery->event,
                'status' => $delivery->status,
                'created_at' => $delivery->created_at->toIso8601String(),
            ];

            return response()->json([
                'message' => 'Webhook sent successfully',
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error sending webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error sending webhook: ' . $e->getMessage(),
                'success' => false,
            ], 500);
        }
    }

    /**
     * Get webhook metrics.
     */
    final public function getMetrics(Request $request): JsonResponse
    {
        try {
            // Get the time period
            $days = $request->query('days', 30);

            // Get metrics
            $metrics = Owlery::metrics((int) $days);

            return response()->json([
                'message' => 'Webhook metrics retrieved successfully',
                'success' => true,
                'data' => $metrics,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error retrieving webhook metrics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error retrieving webhook metrics: ' . $e->getMessage(),
                'success' => false,
            ], 500);
        }
    }
}
