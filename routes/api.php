<?php

use Illuminate\Support\Facades\Route;
use WizardingCode\WebhookOwlery\Http\Controllers\WebhookController;
use WizardingCode\WebhookOwlery\Http\Controllers\WebhookDeliveryController;
use WizardingCode\WebhookOwlery\Http\Controllers\WebhookSubscriptionController;

/*
|--------------------------------------------------------------------------
| Webhook API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your webhook system. These
| routes are loaded by the WebhookOwleryServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy!
|
*/

/**
 * Webhook Receiving Routes
 */
Route::prefix('receive')->group(function () {
    // Generic webhook handler
    Route::post('{source}', [WebhookController::class, 'handle'])
        ->name('webhook.handle')
        ->middleware(['webhook.ratelimit', 'webhook.signature']);

    // Provider-specific handlers
    Route::prefix('provider')->group(function () {
        Route::post('stripe', [WebhookController::class, 'handleProvider'])
            ->name('webhook.provider.stripe')
            ->defaults('provider', 'stripe')
            ->middleware(['webhook.ratelimit', 'webhook.signature:stripe']);

        Route::post('github', [WebhookController::class, 'handleProvider'])
            ->name('webhook.provider.github')
            ->defaults('provider', 'github')
            ->middleware(['webhook.ratelimit', 'webhook.signature:github']);
    });
});

/**
 * Webhook Endpoint Management Routes
 */
Route::prefix('endpoints')->group(function () {
    // List endpoints
    Route::get('/', [WebhookController::class, 'listEndpoints'])
        ->name('webhook.endpoints.index');

    // Create endpoint
    Route::post('/', [WebhookController::class, 'createEndpoint'])
        ->name('webhook.endpoints.store');

    // Get single endpoint
    Route::get('{id}', [WebhookController::class, 'getEndpoint'])
        ->name('webhook.endpoints.show');

    // Update endpoint
    Route::put('{id}', [WebhookController::class, 'updateEndpoint'])
        ->name('webhook.endpoints.update');

    // Delete endpoint
    Route::delete('{id}', [WebhookController::class, 'deleteEndpoint'])
        ->name('webhook.endpoints.destroy');
});

/**
 * Webhook Subscription Management Routes
 */
Route::prefix('subscriptions')->group(function () {
    // List subscriptions
    Route::get('/', [WebhookSubscriptionController::class, 'index'])
        ->name('webhook.subscriptions.index');

    // Create subscription
    Route::post('/', [WebhookSubscriptionController::class, 'store'])
        ->name('webhook.subscriptions.store');

    // Get single subscription
    Route::get('{id}', [WebhookSubscriptionController::class, 'show'])
        ->name('webhook.subscriptions.show');

    // Update subscription
    Route::put('{id}', [WebhookSubscriptionController::class, 'update'])
        ->name('webhook.subscriptions.update');

    // Delete subscription
    Route::delete('{id}', [WebhookSubscriptionController::class, 'destroy'])
        ->name('webhook.subscriptions.destroy');

    // Activate subscription
    Route::post('{id}/activate', [WebhookSubscriptionController::class, 'activate'])
        ->name('webhook.subscriptions.activate');

    // Deactivate subscription
    Route::post('{id}/deactivate', [WebhookSubscriptionController::class, 'deactivate'])
        ->name('webhook.subscriptions.deactivate');

    // Get subscription stats
    Route::get('{id}/stats', [WebhookSubscriptionController::class, 'stats'])
        ->name('webhook.subscriptions.stats');
});

/**
 * Webhook Delivery Management Routes
 */
Route::prefix('deliveries')->group(function () {
    // List deliveries
    Route::get('/', [WebhookDeliveryController::class, 'index'])
        ->name('webhook.deliveries.index');

    // Get single delivery
    Route::get('{id}', [WebhookDeliveryController::class, 'show'])
        ->name('webhook.deliveries.show');

    // Retry delivery
    Route::post('{id}/retry', [WebhookDeliveryController::class, 'retry'])
        ->name('webhook.deliveries.retry');

    // Cancel delivery
    Route::post('{id}/cancel', [WebhookDeliveryController::class, 'cancel'])
        ->name('webhook.deliveries.cancel');

    // Get delivery stats
    Route::get('stats', [WebhookDeliveryController::class, 'stats'])
        ->name('webhook.deliveries.stats');
});

/**
 * Webhook Sending Routes
 */
Route::prefix('send')->group(function () {
    // Send webhook
    Route::post('/', [WebhookController::class, 'sendWebhook'])
        ->name('webhook.send');
});

/**
 * Webhook Metrics Routes
 */
Route::prefix('metrics')->group(function () {
    // Get webhook metrics
    Route::get('/', [WebhookController::class, 'getMetrics'])
        ->name('webhook.metrics');
});

/**
 * Test-specific routes
 */
if (app()->environment('testing')) {
    // Direct endpoint handling for tests - used in WebhookEndpointTest
    Route::post('webhooks/{identifier}', [WebhookController::class, 'handle'])
        ->name('webhook.test.handle')
        ->middleware(['webhook.ratelimit', 'webhook.signature']);
}
