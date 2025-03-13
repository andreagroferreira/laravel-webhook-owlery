<?php

namespace WizardingCode\WebhookOwlery\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use WizardingCode\WebhookOwlery\Facades\Owlery;
use WizardingCode\WebhookOwlery\Models\WebhookDelivery;

class WebhookDeliveryController extends Controller
{
    /**
     * List webhook deliveries.
     */
    final public function index(Request $request): JsonResponse
    {
        try {
            $filters = [];
            $perPage = $request->query('per_page', 15);

            // Extract filters from query parameters
            if ($request->has('status')) {
                $filters['status'] = $request->query('status');
            }

            if ($request->has('endpoint_id')) {
                $filters['webhook_endpoint_id'] = $request->query('endpoint_id');
            }

            if ($request->has('event')) {
                $filters['event'] = $request->query('event');
            }

            if ($request->has('success')) {
                $filters['success'] = filter_var($request->query('success'), FILTER_VALIDATE_BOOLEAN);
            }

            if ($request->has('start_date')) {
                $filters['start_date'] = $request->query('start_date');
            }

            if ($request->has('end_date')) {
                $filters['end_date'] = $request->query('end_date');
            }

            // Get deliveries from repository
            $deliveries = Owlery::repository()->findDeliveries($filters, (int) $perPage);

            // Transform the deliveries
            $result = $deliveries->map(function ($delivery) {
                return [
                    'id' => $delivery->id,
                    'uuid' => $delivery->uuid,
                    'webhook_endpoint_id' => $delivery->webhook_endpoint_id,
                    'endpoint_name' => $delivery->endpoint ? $delivery->endpoint->name : null,
                    'destination' => $delivery->destination,
                    'event' => $delivery->event,
                    'status' => $delivery->status,
                    'attempt' => $delivery->attempt,
                    'max_attempts' => $delivery->max_attempts,
                    'last_attempt_at' => $delivery->last_attempt_at ? $delivery->last_attempt_at->toIso8601String() : null,
                    'next_attempt_at' => $delivery->next_attempt_at ? $delivery->next_attempt_at->toIso8601String() : null,
                    'status_code' => $delivery->status_code,
                    'success' => $delivery->success,
                    'created_at' => $delivery->created_at->toIso8601String(),
                    'updated_at' => $delivery->updated_at->toIso8601String(),
                ];
            });

            // Return paginated response
            return response()->json([
                'message' => 'Webhook deliveries retrieved successfully',
                'success' => true,
                'data' => [
                    'deliveries' => $result,
                    'pagination' => [
                        'total' => $deliveries->total(),
                        'per_page' => $deliveries->perPage(),
                        'current_page' => $deliveries->currentPage(),
                        'last_page' => $deliveries->lastPage(),
                        'from' => $deliveries->firstItem(),
                        'to' => $deliveries->lastItem(),
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error retrieving webhook deliveries', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error retrieving webhook deliveries: ' . $e->getMessage(),
                'success' => false,
            ], 500);
        }
    }

    /**
     * Get a specific webhook delivery.
     *
     * @param string $id Delivery ID or UUID
     */
    final public function show(string $id): JsonResponse
    {
        try {
            $delivery = Owlery::repository()->getDelivery($id);

            if (! $delivery) {
                return response()->json([
                    'message' => 'Webhook delivery not found',
                    'success' => false,
                ], 404);
            }

            // Transform the delivery
            $result = [
                'id' => $delivery->id,
                'uuid' => $delivery->uuid,
                'webhook_endpoint_id' => $delivery->webhook_endpoint_id,
                'endpoint_name' => $delivery->endpoint ? $delivery->endpoint->name : null,
                'destination' => $delivery->destination,
                'event' => $delivery->event,
                'payload' => $delivery->payload,
                'headers' => $delivery->headers,
                'status' => $delivery->status,
                'attempt' => $delivery->attempt,
                'max_attempts' => $delivery->max_attempts,
                'last_attempt_at' => $delivery->last_attempt_at ? $delivery->last_attempt_at->toIso8601String() : null,
                'next_attempt_at' => $delivery->next_attempt_at ? $delivery->next_attempt_at->toIso8601String() : null,
                'status_code' => $delivery->status_code,
                'response_body' => $delivery->response_body,
                'response_headers' => $delivery->response_headers,
                'response_time_ms' => $delivery->response_time_ms,
                'error_message' => $delivery->error_message,
                'error_detail' => $delivery->error_detail,
                'success' => $delivery->success,
                'created_at' => $delivery->created_at->toIso8601String(),
                'updated_at' => $delivery->updated_at->toIso8601String(),
                'metadata' => $delivery->metadata,
            ];

            return response()->json([
                'message' => 'Webhook delivery retrieved successfully',
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error retrieving webhook delivery', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error retrieving webhook delivery: ' . $e->getMessage(),
                'success' => false,
            ], 500);
        }
    }

    /**
     * Retry a webhook delivery.
     *
     * @param string $id Delivery ID or UUID
     */
    final public function retry(string $id, Request $request): JsonResponse
    {
        try {
            $delivery = Owlery::repository()->getDelivery($id);

            if (! $delivery) {
                return response()->json([
                    'message' => 'Webhook delivery not found',
                    'success' => false,
                ], 404);
            }

            // Check if delivery can be retried
            if (! $delivery->canBeRetried()) {
                return response()->json([
                    'message' => 'Webhook delivery cannot be retried',
                    'success' => false,
                    'errors' => [
                        'status' => $delivery->status,
                        'attempt' => $delivery->attempt,
                        'max_attempts' => $delivery->max_attempts,
                    ],
                ], 422);
            }

            // Get retry options
            $options = [];

            if ($request->has('queue')) {
                $options['queue'] = filter_var($request->input('queue'), FILTER_VALIDATE_BOOLEAN);
            }

            if ($request->has('headers')) {
                $options['headers'] = $request->input('headers');
            }

            if ($request->has('timeout')) {
                $options['timeout'] = (int) $request->input('timeout');
            }

            // Retry the delivery
            $retried = Owlery::dispatcher()->retry($delivery, $options);

            // Transform the result
            $result = [
                'id' => $retried->id,
                'uuid' => $retried->uuid,
                'status' => $retried->status,
                'attempt' => $retried->attempt,
                'last_attempt_at' => $retried->last_attempt_at ? $retried->last_attempt_at->toIso8601String() : null,
                'next_attempt_at' => $retried->next_attempt_at ? $retried->next_attempt_at->toIso8601String() : null,
                'updated_at' => $retried->updated_at->toIso8601String(),
            ];

            return response()->json([
                'message' => 'Webhook delivery queued for retry',
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error retrying webhook delivery', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error retrying webhook delivery: ' . $e->getMessage(),
                'success' => false,
            ], 500);
        }
    }

    /**
     * Cancel a webhook delivery.
     *
     * @param string $id Delivery ID or UUID
     */
    final public function cancel(string $id, Request $request): JsonResponse
    {
        try {
            $delivery = Owlery::repository()->getDelivery($id);

            if (! $delivery) {
                return response()->json([
                    'message' => 'Webhook delivery not found',
                    'success' => false,
                ], 404);
            }

            // Check if delivery can be cancelled
            if (! in_array($delivery->status, [WebhookDelivery::STATUS_PENDING, WebhookDelivery::STATUS_RETRYING], true)) {
                return response()->json([
                    'message' => 'Webhook delivery cannot be cancelled',
                    'success' => false,
                    'errors' => [
                        'status' => $delivery->status,
                    ],
                ], 422);
            }

            // Get reason if provided
            $reason = $request->input('reason');

            // Cancel the delivery
            $cancelled = Owlery::dispatcher()->cancel($delivery, $reason);

            // Transform the result
            $result = [
                'id' => $cancelled->id,
                'uuid' => $cancelled->uuid,
                'status' => $cancelled->status,
                'updated_at' => $cancelled->updated_at->toIso8601String(),
            ];

            return response()->json([
                'message' => 'Webhook delivery cancelled successfully',
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error cancelling webhook delivery', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error cancelling webhook delivery: ' . $e->getMessage(),
                'success' => false,
            ], 500);
        }
    }

    /**
     * Get webhook delivery statistics.
     */
    final public function stats(Request $request): JsonResponse
    {
        try {
            // Get period to analyze
            $days = (int) $request->query('days', 7);
            $startDate = now()->subDays($days)->startOfDay();

            // Get filters
            $filters = [
                'start_date' => $startDate,
            ];

            if ($request->has('endpoint_id')) {
                $filters['webhook_endpoint_id'] = $request->query('endpoint_id');
            }

            if ($request->has('event')) {
                $filters['event'] = $request->query('event');
            }

            // Get deliveries
            $deliveries = Owlery::repository()->findDeliveries($filters, 0);

            // Calculate basic stats
            $totalCount = $deliveries->count();
            $successCount = $deliveries->where('success', true)->count();
            $failureCount = $deliveries->where('success', false)->count();
            $pendingCount = $deliveries->whereIn('status', [WebhookDelivery::STATUS_PENDING, WebhookDelivery::STATUS_RETRYING])->count();

            $successRate = $totalCount > 0 ? round(($successCount / $totalCount) * 100, 2) : 0;
            $averageResponseTime = $deliveries->whereNotNull('response_time_ms')->average('response_time_ms') ?: 0;

            // Calculate average attempts for failures
            $failedDeliveries = $deliveries->where('status', WebhookDelivery::STATUS_FAILED);
            $averageAttempts = $failedDeliveries->count() > 0 ? $failedDeliveries->average('attempt') : 0;

            // Daily breakdown
            $dailyStats = [];
            for ($i = 0; $i < $days; $i++) {
                $date = now()->subDays($i)->format('Y-m-d');
                $dayStart = now()->subDays($i)->startOfDay();
                $dayEnd = now()->subDays($i)->endOfDay();

                $dayDeliveries = $deliveries->filter(function ($delivery) use ($dayStart, $dayEnd) {
                    return $delivery->created_at >= $dayStart && $delivery->created_at <= $dayEnd;
                });

                $daySuccessCount = $dayDeliveries->where('success', true)->count();
                $dayTotalCount = $dayDeliveries->count();

                $dailyStats[$date] = [
                    'total' => $dayTotalCount,
                    'success' => $daySuccessCount,
                    'failed' => $dayDeliveries->where('success', false)->count(),
                    'success_rate' => $dayTotalCount > 0 ? round(($daySuccessCount / $dayTotalCount) * 100, 2) : 0,
                ];
            }

            // Return stats
            return response()->json([
                'message' => 'Webhook delivery statistics retrieved successfully',
                'success' => true,
                'data' => [
                    'period' => [
                        'days' => $days,
                        'start_date' => $startDate->toDateTimeString(),
                        'end_date' => now()->toDateTimeString(),
                    ],
                    'summary' => [
                        'total_deliveries' => $totalCount,
                        'successful_deliveries' => $successCount,
                        'failed_deliveries' => $failureCount,
                        'pending_deliveries' => $pendingCount,
                        'success_rate' => $successRate,
                        'average_response_time_ms' => round($averageResponseTime, 2),
                        'average_attempts_for_failures' => round($averageAttempts, 2),
                    ],
                    'daily' => $dailyStats,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error retrieving webhook delivery statistics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error retrieving webhook delivery statistics: ' . $e->getMessage(),
                'success' => false,
            ], 500);
        }
    }
}
