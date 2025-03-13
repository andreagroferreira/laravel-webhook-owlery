<?php

namespace WizardingCode\WebhookOwlery\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use WizardingCode\WebhookOwlery\Facades\Owlery;
use WizardingCode\WebhookOwlery\Http\Requests\WebhookSubscriptionRequest;

class WebhookSubscriptionController extends Controller
{
    /**
     * List all webhook subscriptions.
     */
    final public function index(Request $request): JsonResponse
    {
        try {
            $filters = [];

            // Extract filters from query params
            if ($request->has('active')) {
                $filters['is_active'] = filter_var($request->query('active'), FILTER_VALIDATE_BOOLEAN);
            }

            if ($request->has('event_type')) {
                $filters['event_type'] = $request->query('event_type');
            }

            if ($request->has('endpoint_id')) {
                $filters['webhook_endpoint_id'] = $request->query('endpoint_id');
            }

            // Get subscriptions from the repository
            $subscriptions = Owlery::repository()->findSubscriptions($filters);

            // Transform the subscriptions
            $result = $subscriptions->map(function ($subscription) {
                return [
                    'id' => $subscription->id,
                    'uuid' => $subscription->uuid,
                    'webhook_endpoint_id' => $subscription->webhook_endpoint_id,
                    'endpoint_name' => $subscription->endpoint ? $subscription->endpoint->name : null,
                    'event_type' => $subscription->event_type,
                    'description' => $subscription->description,
                    'is_active' => $subscription->is_active,
                    'activated_at' => $subscription->activated_at ? $subscription->activated_at->toIso8601String() : null,
                    'deactivated_at' => $subscription->deactivated_at ? $subscription->deactivated_at->toIso8601String() : null,
                    'expires_at' => $subscription->expires_at ? $subscription->expires_at->toIso8601String() : null,
                    'max_deliveries' => $subscription->max_deliveries,
                    'delivery_count' => $subscription->delivery_count,
                    'created_at' => $subscription->created_at->toIso8601String(),
                    'updated_at' => $subscription->updated_at->toIso8601String(),
                ];
            });

            return response()->json([
                'message' => 'Webhook subscriptions retrieved successfully',
                'success' => true,
                'data' => [
                    'subscriptions' => $result,
                    'count' => $result->count(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error retrieving webhook subscriptions', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error retrieving webhook subscriptions: ' . $e->getMessage(),
                'success' => false,
            ], 500);
        }
    }

    /**
     * Get a specific webhook subscription.
     *
     * @param string $id Subscription ID or UUID
     */
    final public function show(string $id): JsonResponse
    {
        try {
            $subscription = Owlery::repository()->getSubscription($id);

            if (! $subscription) {
                return response()->json([
                    'message' => 'Webhook subscription not found',
                    'success' => false,
                ], 404);
            }

            // Transform the subscription
            $result = [
                'id' => $subscription->id,
                'uuid' => $subscription->uuid,
                'webhook_endpoint_id' => $subscription->webhook_endpoint_id,
                'endpoint_name' => $subscription->endpoint ? $subscription->endpoint->name : null,
                'endpoint_url' => $subscription->endpoint ? $subscription->endpoint->url : null,
                'event_type' => $subscription->event_type,
                'event_filters' => $subscription->event_filters,
                'description' => $subscription->description,
                'is_active' => $subscription->is_active,
                'activated_at' => $subscription->activated_at ? $subscription->activated_at->toIso8601String() : null,
                'deactivated_at' => $subscription->deactivated_at ? $subscription->deactivated_at->toIso8601String() : null,
                'expires_at' => $subscription->expires_at ? $subscription->expires_at->toIso8601String() : null,
                'max_deliveries' => $subscription->max_deliveries,
                'delivery_count' => $subscription->delivery_count,
                'created_at' => $subscription->created_at->toIso8601String(),
                'updated_at' => $subscription->updated_at->toIso8601String(),
                'created_by' => $subscription->created_by,
                'updated_by' => $subscription->updated_by,
            ];

            return response()->json([
                'message' => 'Webhook subscription retrieved successfully',
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error retrieving webhook subscription', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error retrieving webhook subscription: ' . $e->getMessage(),
                'success' => false,
            ], 500);
        }
    }

    /**
     * Create a new webhook subscription.
     */
    final public function store(WebhookSubscriptionRequest $request): JsonResponse
    {
        try {
            // Create the subscription using the Owlery facade
            $subscription = Owlery::subscribe(
                $request->input('webhook_endpoint_id'),
                $request->input('event_type'),
                $request->input('event_filters', []),
                $request->except(['webhook_endpoint_id', 'event_type', 'event_filters'])
            );

            // Transform the subscription
            $result = [
                'id' => $subscription->id,
                'uuid' => $subscription->uuid,
                'webhook_endpoint_id' => $subscription->webhook_endpoint_id,
                'event_type' => $subscription->event_type,
                'description' => $subscription->description,
                'is_active' => $subscription->is_active,
                'created_at' => $subscription->created_at->toIso8601String(),
                'updated_at' => $subscription->updated_at->toIso8601String(),
            ];

            return response()->json([
                'message' => 'Webhook subscription created successfully',
                'success' => true,
                'data' => $result,
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Error creating webhook subscription', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error creating webhook subscription: ' . $e->getMessage(),
                'success' => false,
            ], 500);
        }
    }

    /**
     * Update a webhook subscription.
     *
     * @param string $id Subscription ID or UUID
     */
    final public function update(string $id, WebhookSubscriptionRequest $request): JsonResponse
    {
        try {
            // Get the subscription
            $subscription = Owlery::repository()->getSubscription($id);

            if (! $subscription) {
                return response()->json([
                    'message' => 'Webhook subscription not found',
                    'success' => false,
                ], 404);
            }

            // Update the subscription
            foreach ($request->validated() as $key => $value) {
                if ($request->has($key)) {
                    $subscription->{$key} = $value;
                }
            }

            // Update the updated_by field if provided
            if ($request->has('updated_by')) {
                $subscription->updated_by = $request->input('updated_by');
            }

            // Save the changes
            $subscription->save();

            // Transform the subscription
            $result = [
                'id' => $subscription->id,
                'uuid' => $subscription->uuid,
                'webhook_endpoint_id' => $subscription->webhook_endpoint_id,
                'event_type' => $subscription->event_type,
                'description' => $subscription->description,
                'is_active' => $subscription->is_active,
                'updated_at' => $subscription->updated_at->toIso8601String(),
            ];

            return response()->json([
                'message' => 'Webhook subscription updated successfully',
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error updating webhook subscription', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error updating webhook subscription: ' . $e->getMessage(),
                'success' => false,
            ], 500);
        }
    }

    /**
     * Delete a webhook subscription.
     *
     * @param string $id Subscription ID or UUID
     */
    final public function destroy(string $id): JsonResponse
    {
        try {
            // Get the subscription
            $subscription = Owlery::repository()->getSubscription($id);

            if (! $subscription) {
                return response()->json([
                    'message' => 'Webhook subscription not found',
                    'success' => false,
                ], 404);
            }

            // Delete the subscription
            $subscription->delete();

            return response()->json([
                'message' => 'Webhook subscription deleted successfully',
                'success' => true,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error deleting webhook subscription', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error deleting webhook subscription: ' . $e->getMessage(),
                'success' => false,
            ], 500);
        }
    }

    /**
     * Activate a webhook subscription.
     *
     * @param string $id Subscription ID or UUID
     */
    final public function activate(string $id): JsonResponse
    {
        try {
            // Get the subscription
            $subscription = Owlery::repository()->getSubscription($id);

            if (! $subscription) {
                return response()->json([
                    'message' => 'Webhook subscription not found',
                    'success' => false,
                ], 404);
            }

            // Check if already active
            if ($subscription->is_active) {
                return response()->json([
                    'message' => 'Webhook subscription is already active',
                    'success' => true,
                    'data' => ['id' => $subscription->id, 'uuid' => $subscription->uuid],
                ]);
            }

            // Activate the subscription
            $subscription->is_active = true;
            $subscription->activated_at = now();
            $subscription->deactivated_at = null;
            $subscription->save();

            return response()->json([
                'message' => 'Webhook subscription activated successfully',
                'success' => true,
                'data' => [
                    'id' => $subscription->id,
                    'uuid' => $subscription->uuid,
                    'activated_at' => $subscription->activated_at->toIso8601String(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error activating webhook subscription', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error activating webhook subscription: ' . $e->getMessage(),
                'success' => false,
            ], 500);
        }
    }

    /**
     * Deactivate a webhook subscription.
     *
     * @param string $id Subscription ID or UUID
     */
    public function deactivate(string $id): JsonResponse
    {
        try {
            // Get the subscription
            $subscription = Owlery::repository()->getSubscription($id);

            if (! $subscription) {
                return response()->json([
                    'message' => 'Webhook subscription not found',
                    'success' => false,
                ], 404);
            }

            // Check if already inactive
            if (! $subscription->is_active) {
                return response()->json([
                    'message' => 'Webhook subscription is already inactive',
                    'success' => true,
                    'data' => ['id' => $subscription->id, 'uuid' => $subscription->uuid],
                ]);
            }

            // Deactivate the subscription
            $subscription->is_active = false;
            $subscription->deactivated_at = now();
            $subscription->save();

            return response()->json([
                'message' => 'Webhook subscription deactivated successfully',
                'success' => true,
                'data' => [
                    'id' => $subscription->id,
                    'uuid' => $subscription->uuid,
                    'deactivated_at' => $subscription->deactivated_at->toIso8601String(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error deactivating webhook subscription', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error deactivating webhook subscription: ' . $e->getMessage(),
                'success' => false,
            ], 500);
        }
    }

    /**
     * Get subscription delivery stats.
     *
     * @param string $id Subscription ID or UUID
     */
    final public function stats(string $id): JsonResponse
    {
        try {
            // Get the subscription
            $subscription = Owlery::repository()->getSubscription($id);

            if (! $subscription) {
                return response()->json([
                    'message' => 'Webhook subscription not found',
                    'success' => false,
                ], 404);
            }

            // Get deliveries for this subscription
            $deliveries = Owlery::repository()->findDeliveries([
                'webhook_endpoint_id' => $subscription->webhook_endpoint_id,
                'event' => $subscription->event_type,
            ]);

            // Calculate stats
            $totalDeliveries = $deliveries->count();
            $successfulDeliveries = $deliveries->where('status', 'success')->count();
            $failedDeliveries = $deliveries->where('status', 'failed')->count();
            $pendingDeliveries = $deliveries->whereIn('status', ['pending', 'retrying'])->count();

            // Calculate success rate
            $successRate = $totalDeliveries > 0 ? round(($successfulDeliveries / $totalDeliveries) * 100, 2) : 0;

            return response()->json([
                'message' => 'Webhook subscription stats retrieved successfully',
                'success' => true,
                'data' => [
                    'subscription_id' => $subscription->id,
                    'subscription_uuid' => $subscription->uuid,
                    'event_type' => $subscription->event_type,
                    'is_active' => $subscription->is_active,
                    'stats' => [
                        'total_deliveries' => $totalDeliveries,
                        'successful_deliveries' => $successfulDeliveries,
                        'failed_deliveries' => $failedDeliveries,
                        'pending_deliveries' => $pendingDeliveries,
                        'success_rate' => $successRate,
                        'delivery_count' => $subscription->delivery_count,
                        'max_deliveries' => $subscription->max_deliveries,
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error retrieving webhook subscription stats', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error retrieving webhook subscription stats: ' . $e->getMessage(),
                'success' => false,
            ], 500);
        }
    }
}
