<?php

namespace WizardingCode\WebhookOwlery;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use WizardingCode\WebhookOwlery\Commands\CleanupWebhooksCommand;
use WizardingCode\WebhookOwlery\Commands\GenerateWebhookSecretCommand;
use WizardingCode\WebhookOwlery\Commands\ListWebhookEndpointsCommand;
use WizardingCode\WebhookOwlery\Contracts\CircuitBreakerContract;
use WizardingCode\WebhookOwlery\Contracts\OwleryContract;
use WizardingCode\WebhookOwlery\Contracts\SignatureValidatorContract;
use WizardingCode\WebhookOwlery\Contracts\WebhookDispatcherContract;
use WizardingCode\WebhookOwlery\Contracts\WebhookReceiverContract;
use WizardingCode\WebhookOwlery\Contracts\WebhookRepositoryContract;
use WizardingCode\WebhookOwlery\Facades\Owlery as OwleryFacade;
use WizardingCode\WebhookOwlery\Facades\WebhookDispatcher as WebhookDispatcherFacade;
use WizardingCode\WebhookOwlery\Facades\WebhookReceiver as WebhookReceiverFacade;
use WizardingCode\WebhookOwlery\Repositories\EloquentWebhookRepository;
use WizardingCode\WebhookOwlery\Repositories\RedisCircuitBreaker;
use WizardingCode\WebhookOwlery\Services\Owlery as OwleryService;
use WizardingCode\WebhookOwlery\Services\WebhookDispatcher as WebhookDispatcherService;
use WizardingCode\WebhookOwlery\Services\WebhookReceiver as WebhookReceiverService;
use WizardingCode\WebhookOwlery\Support\WebhookPayloadTransformer;
use WizardingCode\WebhookOwlery\Support\WebhookResponseAnalyzer;
use WizardingCode\WebhookOwlery\Validators\HmacSignatureValidator;

class WebhookOwleryServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/webhook-owlery.php', 'webhook-owlery'
        );

        // Bind interfaces to implementations
        $this->app->bind(WebhookRepositoryContract::class, EloquentWebhookRepository::class);
        $this->app->bind(SignatureValidatorContract::class, HmacSignatureValidator::class);
        $this->app->bind(CircuitBreakerContract::class, RedisCircuitBreaker::class);

        // Register WebhookDispatcher
        $this->app->singleton(WebhookDispatcherContract::class, function ($app) {
            return new WebhookDispatcherService(
                $app->make(WebhookRepositoryContract::class),
                null,
                null,
                $app->make(CircuitBreakerContract::class),
                $app->make(WebhookResponseAnalyzer::class)
            );
        });

        // Register WebhookReceiver
        $this->app->singleton(WebhookReceiverContract::class, function ($app) {
            return new WebhookReceiverService(
                $app->make(WebhookRepositoryContract::class),
                $app->make(SignatureValidatorContract::class)
            );
        });

        // Register main Owlery service
        $this->app->singleton(OwleryContract::class, function ($app) {
            return new OwleryService(
                $app->make(WebhookRepositoryContract::class),
                $app->make(WebhookReceiverContract::class),
                $app->make(WebhookDispatcherContract::class),
                null, // WebhookSubscriptionContract is not yet implemented
                $app->make(CircuitBreakerContract::class)
            );
        });

        // Bind to the facade accessor name
        $this->app->alias(OwleryContract::class, 'webhook-owlery');

        // Register support classes
        $this->app->bind(WebhookPayloadTransformer::class);
        $this->app->bind(WebhookResponseAnalyzer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publishing configuration
        $this->publishes([
            __DIR__ . '/../config/webhook-owlery.php' => config_path('webhook-owlery.php'),
        ], 'config');

        // Publishing migrations
        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'migrations');

        // Load migrations directly from package if auto-loading is enabled
        // Use config to determine if migrations should be auto-loaded
        if (config('webhook-owlery.migrations.auto_load', false)) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                CleanupWebhooksCommand::class,
                GenerateWebhookSecretCommand::class,
                ListWebhookEndpointsCommand::class,
            ]);
        }

        // Register middleware
        $this->registerMiddleware();

        // Load routes
        $this->registerRoutes();

        // Register facades
        $this->app->booting(function () {
            $loader = AliasLoader::getInstance();
            $loader->alias('Owlery', OwleryFacade::class);
            $loader->alias('WebhookDispatcher', WebhookDispatcherFacade::class);
            $loader->alias('WebhookReceiver', WebhookReceiverFacade::class);
        });
    }

    /**
     * Register the package routes.
     */
    protected function registerRoutes(): void
    {
        if (config('webhook-owlery.routes.enabled', true)) {
            Route::group($this->routeConfiguration(), function () {
                $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
            });
        }
    }

    /**
     * Get the route group configuration.
     */
    protected function routeConfiguration(): array
    {
        return [
            'prefix' => config('webhook-owlery.routes.prefix', 'api/webhooks'),
            'middleware' => config('webhook-owlery.routes.middleware', ['api']),
        ];
    }

    /**
     * Register the middleware.
     */
    protected function registerMiddleware(): void
    {
        $router = $this->app['router'];

        // Register the middleware aliases
        $router->aliasMiddleware('webhook.signature', \WizardingCode\WebhookOwlery\Http\Middleware\ValidateWebhookSignature::class);
        $router->aliasMiddleware('webhook.ratelimit', \WizardingCode\WebhookOwlery\Http\Middleware\WebhookRateLimiter::class);

        // Register middleware groups if configured
        if (config('webhook-owlery.routes.register_middleware_group', true)) {
            $router->middlewareGroup('webhook', [
                'webhook.ratelimit',
                'webhook.signature',
            ]);
        }
    }
}
