<?php

namespace WizardingCode\WebhookOwlery\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase as Orchestra;
use WizardingCode\WebhookOwlery\WebhookOwleryServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'WizardingCode\\WebhookOwlery\\Database\\Factories\\' . class_basename($modelName) . 'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            WebhookOwleryServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $migration = include __DIR__ . '/../database/migrations/create_webhook_endpoints_table.php';
        $migration->up();

        $migration = include __DIR__ . '/../database/migrations/create_webhook_subscriptions_table.php';
        $migration->up();

        $migration = include __DIR__ . '/../database/migrations/create_webhook_events_table.php';
        $migration->up();

        $migration = include __DIR__ . '/../database/migrations/create_webhook_deliveries_table.php';
        $migration->up();

        // Register webhook routes for testing
        Route::middleware('api')->prefix('api')->group(function () {
            Route::post('/webhooks/test', function () {
                return response()->json(['message' => 'Webhook received']);
            })->name('webhook.test');

            // Simple test route for the webhook endpoint test
            Route::post('/webhooks/test-endpoint', function (\Illuminate\Http\Request $request) {
                return response()->json(['message' => 'Webhook received', 'success' => true]);
            })->name('webhook.test.endpoint');
        });
    }
}
