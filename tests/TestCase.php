<?php

namespace WizardingCode\WebhookOwlery\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Route;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Orchestra\Testbench\TestCase as Orchestra;
use WizardingCode\WebhookOwlery\WebhookOwleryServiceProvider;

class TestCase extends Orchestra
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'WizardingCode\\WebhookOwlery\\Database\\Factories\\' . class_basename($modelName) . 'Factory'
        );
        
        // Ensure repository binding is set correctly
        $this->app->bind(
            \WizardingCode\WebhookOwlery\Contracts\WebhookRepositoryContract::class,
            \WizardingCode\WebhookOwlery\Repositories\EloquentWebhookRepository::class
        );
        
        // Ensure circuit breaker binding is set correctly
        $this->app->bind(
            \WizardingCode\WebhookOwlery\Contracts\CircuitBreakerContract::class,
            \WizardingCode\WebhookOwlery\Repositories\RedisCircuitBreaker::class
        );
        
        // Properly setup event dispatcher
        if (!$this->app->bound('events')) {
            $this->app->singleton('events', function ($app) {
                return new \Illuminate\Events\Dispatcher($app);
            });
        }
    }

    protected function getPackageProviders($app)
    {
        return [
            WebhookOwleryServiceProvider::class,
        ];
    }

    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        // Force config to be loaded
        $app['config']->set('webhook-owlery.routes.enabled', true);
        $app['config']->set('webhook-owlery.routes.prefix', 'api');
        $app['config']->set('webhook-owlery.routes.middleware', ['api']);

        // Add required config items
        $app['config']->set('webhook-owlery.signing.default', 'hmac');
        $app['config']->set('webhook-owlery.signing.hmac.algorithm', 'sha256');
        $app['config']->set('webhook-owlery.signing.hmac.header', 'X-Signature');

        $app['config']->set('webhook-owlery.circuit_breaker.enabled', true);
        $app['config']->set('webhook-owlery.circuit_breaker.threshold', 5);
        $app['config']->set('webhook-owlery.circuit_breaker.recovery_time', 60);
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
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
