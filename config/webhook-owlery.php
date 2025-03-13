<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Webhook Owlery General Configuration
    |--------------------------------------------------------------------------
    |
    | A reliable webhook management system for your Laravel application.
    | Like owls delivering messages in the wizarding world, this package
    | handles webhook delivery with reliability and intelligence.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how routes are registered and what middleware they use.
    | You can disable automatic route registration if you want to define
    | your own routes manually.
    |
    */
    'routes' => [
        // Whether to register the package routes automatically
        'enabled' => true,

        // Prefix for all webhook routes
        'prefix' => 'api/webhooks',

        // Middleware applied to all webhook routes
        'middleware' => ['api'],

        // Whether to register the middleware group 'webhook'
        'register_middleware_group' => true,

        // Customize the middleware for specific route groups
        'middleware_groups' => [
            // Middleware for incoming webhook endpoints
            'incoming' => ['webhook.ratelimit', 'webhook.signature'],

            // Middleware for management endpoints (if you want auth)
            'management' => ['api', 'auth:sanctum'],
        ],

        // Authentication and access to the management API
        'management_auth' => [
            'enabled' => false,
            'middleware' => ['auth:sanctum'],
            'guard' => 'sanctum',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Receiving Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how incoming webhooks are received and processed.
    |
    */
    'receiving' => [
        // Whether signatures are required for incoming webhooks
        'verify_signatures' => true,

        // Reject webhooks with invalid signatures (if false, will just log warnings)
        'require_valid_signature' => true,

        // Default signature validation method
        'signature_validator' => 'hmac',

        // Available signature validators
        'validators' => [
            'hmac' => WizardingCode\WebhookOwlery\Validators\HmacSignatureValidator::class,
            'basic' => WizardingCode\WebhookOwlery\Validators\BasicAuthValidator::class,
            'jwt' => WizardingCode\WebhookOwlery\Validators\JwtSignatureValidator::class,
            'apikey' => WizardingCode\WebhookOwlery\Validators\ApiKeyValidator::class,
            'provider' => WizardingCode\WebhookOwlery\Validators\ProviderSpecificValidator::class,
        ],

        // Headers to check for signatures
        'signature_header' => 'X-Webhook-Signature',

        // Headers to check for event name
        'event_header' => 'X-Webhook-Event',

        // Process webhooks in the background (async)
        'process_async' => true,

        // Maximum number of processing attempts
        'max_attempts' => 3,

        // Processing timeout in seconds
        'timeout' => 60,

        // Retry delay cap for exponential backoff (seconds)
        'retry_delay_cap' => 600,

        // Queue configuration for async processing
        'queue' => 'webhooks-incoming',
        'queue_connection' => env('WEBHOOK_QUEUE_CONNECTION', 'redis'),

        // Custom error handling
        'error_handling' => [
            // What to do when a webhook fails processing
            'on_failure' => 'log', // Options: log, retry, notify, callback

            // Retry policy for failed webhooks
            'retry_failed' => true,

            // Class to notify on failure (must implement Notification contract)
            'notification_class' => null,

            // Callback to execute on failure (must be callable)
            'failure_callback' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Dispatching Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how outgoing webhooks are dispatched to external systems.
    |
    */
    'dispatching' => [
        // Retry strategy for failed deliveries
        'retry_strategy' => 'exponential', // Options: exponential, linear, fixed

        // Default retry delay in seconds
        'retry_delay' => 30,

        // Maximum attempts for delivery
        'max_attempts' => 3,

        // Queue for webhook deliveries
        'queue' => 'webhooks-outgoing',

        // Queue for retry jobs
        'retry_queue' => 'webhooks-retry',

        // Queue connection
        'queue_connection' => env('WEBHOOK_QUEUE_CONNECTION', 'redis'),

        // Circuit breaker configuration
        'circuit_breaker' => [
            'enabled' => true,
            'threshold' => 5,  // Number of failures before circuit opens
            'open_duration' => 300, // 5 minutes in seconds
            'redis_connection' => env('REDIS_CIRCUIT_BREAKER_CONNECTION', null),
            'redis_prefix' => 'webhook-circuit:',
        ],

        // HTTP client configuration
        'http' => [
            'timeout' => 30,
            'connect_timeout' => 10,
            'user_agent' => 'Laravel-Webhook-Owlery/1.0',
            'verify_ssl' => true,
            'http_errors' => true, // Whether to throw exceptions for HTTP errors
        ],

        // Default headers sent with all outgoing webhooks
        'default_headers' => [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'Laravel-Webhook-Owlery/1.0',
        ],

        // Success status codes (2xx by default, but can be customized)
        'success_status_codes' => [200, 201, 202, 203, 204, 205, 206, 207, 208, 226],
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how webhook data is stored and how long it's retained.
    |
    */
    'storage' => [
        // Data retention period in days (0 = keep forever)
        'retention_days' => 30,

        // Queue for cleanup jobs
        'cleanup_queue' => 'webhooks-cleanup',

        // Whether to store full webhook payloads
        'store_payloads' => true,

        // Whether to encrypt webhook payloads
        'encrypt_payloads' => true,

        // Limit payload size (in KB, 0 = no limit)
        'payload_size_limit' => 500,

        // What to do if payload exceeds limit
        'payload_size_handling' => 'truncate', // Options: truncate, fail, ignore

        // Truncation indicator
        'truncation_indicator' => '[Truncated...]',

        // Database configuration
        'database' => [
            'connection' => env('WEBHOOK_DB_CONNECTION', null), // null = default connection
            'endpoints_table' => 'webhook_endpoints',
            'events_table' => 'webhook_events',
            'deliveries_table' => 'webhook_deliveries',
            'subscriptions_table' => 'webhook_subscriptions',
        ],

        // Storage for large payloads
        'disk' => env('WEBHOOK_STORAGE_DISK', 'local'),
        'disk_path' => 'webhook-payloads',
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Configure security options for webhook signing and validation.
    |
    */
    'security' => [
        // Webhook signatures
        'default_signature_key' => env('WEBHOOK_SIGNATURE_KEY', ''),
        'default_algorithm' => 'sha256',
        'require_signatures' => true,
        'timestamp_tolerance' => 300, // Seconds

        // Rate limiting
        'rate_limiting' => [
            'enabled' => true,
            'max_attempts' => 60, // Requests per minute by default
            'decay_minutes' => 1,
            'response_code' => 429, // Too Many Requests

            // Custom scope function for rate limiting
            // Should be a callable that accepts Request and provider
            'scope' => null,
        ],

        // IP address restrictions
        'ip_filtering' => [
            'enabled' => false,
            'allowed_ips' => [],
            'allowed_ranges' => [],
            'block_response_code' => 403, // Forbidden
        ],

        // Webhook handshake
        'handshake' => [
            'enabled' => false,
            'header' => 'X-Webhook-Verify-Token',
            'token' => env('WEBHOOK_VERIFY_TOKEN', ''),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring & Logging
    |--------------------------------------------------------------------------
    |
    | Configure monitoring, alerting and logging options.
    |
    */
    'monitoring' => [
        // Log channel for webhook events
        'log_channel' => env('WEBHOOK_LOG_CHANNEL', null), // null = default channel

        // Log level for different webhook events
        'log_levels' => [
            'received' => 'info',
            'dispatched' => 'info',
            'failed' => 'error',
            'invalid' => 'warning',
        ],

        // Queue for monitoring jobs
        'queue' => 'webhooks-monitoring',

        // Alert thresholds
        'alert_thresholds' => [
            'failure_rate' => 10.0, // Alert if failure rate exceeds this percentage
            'response_time' => 2000, // Alert if average response time exceeds this (ms)
        ],

        // Notification settings
        'notification_class' => env('WEBHOOK_NOTIFICATION_CLASS'),
        'channels' => ['mail', 'slack'],
        'recipients' => [
            env('WEBHOOK_ALERT_EMAIL'),
        ],

        // Integration with Laravel Telescope (if installed)
        'telescope' => [
            'enabled' => env('WEBHOOK_TELESCOPE_ENABLED', true),
            'tag' => 'webhooks',
        ],

        // Health check endpoint
        'health_check' => [
            'enabled' => true,
            'route' => '/api/webhooks/health',
            'middleware' => ['api'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider-Specific Configurations
    |--------------------------------------------------------------------------
    |
    | Configurations for specific webhook providers like Stripe, GitHub, etc.
    | Each provider can have its own signature validation, event mapping and
    | other custom configurations.
    |
    */
    'providers' => [
        'stripe' => [
            'display_name' => 'Stripe',
            'signature_validator' => 'provider',
            'signature_header' => 'Stripe-Signature',
            'timestamp_header' => 't',
            'timestamp_required' => true,
            'algorithm' => 'sha256',
            'secret_key' => env('STRIPE_WEBHOOK_SECRET'),
            'rate_limit' => 100, // Higher limit for Stripe
            'events' => [
                // Map Stripe events to your app's event handlers
                'charge.succeeded' => [
                    'handler' => 'App\\Handlers\\StripeChargeHandler',
                    'method' => 'handleChargeSucceeded',
                ],
                'invoice.payment_succeeded' => [
                    'handler' => 'App\\Handlers\\StripeInvoiceHandler',
                    'method' => 'handlePaymentSucceeded',
                ],
                'invoice.payment_failed' => [
                    'handler' => 'App\\Handlers\\StripeInvoiceHandler',
                    'method' => 'handlePaymentFailed',
                ],
                'customer.subscription.updated' => [
                    'handler' => 'App\\Handlers\\StripeSubscriptionHandler',
                    'method' => 'handleSubscriptionUpdated',
                ],
            ],
        ],

        'github' => [
            'display_name' => 'GitHub',
            'signature_validator' => 'provider',
            'signature_header' => 'X-Hub-Signature-256',
            'signature_prefix' => 'sha256=',
            'algorithm' => 'sha256',
            'secret_key' => env('GITHUB_WEBHOOK_SECRET'),
            'events' => [
                'push' => [
                    'handler' => 'App\\Handlers\\GitHubHandler',
                    'method' => 'handlePush',
                ],
                'pull_request' => [
                    'handler' => 'App\\Handlers\\GitHubHandler',
                    'method' => 'handlePullRequest',
                ],
                'issues' => [
                    'handler' => 'App\\Handlers\\GitHubHandler',
                    'method' => 'handleIssues',
                ],
            ],
        ],

        'shopify' => [
            'display_name' => 'Shopify',
            'signature_validator' => 'provider',
            'signature_header' => 'X-Shopify-Hmac-Sha256',
            'algorithm' => 'sha256',
            'secret_key' => env('SHOPIFY_WEBHOOK_SECRET'),
            'events' => [
                'orders/create' => [
                    'handler' => 'App\\Handlers\\ShopifyOrderHandler',
                    'method' => 'handleOrderCreated',
                ],
                'products/update' => [
                    'handler' => 'App\\Handlers\\ShopifyProductHandler',
                    'method' => 'handleProductUpdated',
                ],
            ],
        ],

        'slack' => [
            'display_name' => 'Slack',
            'signature_validator' => 'provider',
            'signature_header' => 'X-Slack-Signature',
            'timestamp_header' => 'X-Slack-Request-Timestamp',
            'algorithm' => 'sha256',
            'secret_key' => env('SLACK_SIGNING_SECRET'),
            'events' => [
                'event_callback' => [
                    'handler' => 'App\\Handlers\\SlackEventHandler',
                    'method' => 'handleEvent',
                ],
                'url_verification' => [
                    'handler' => 'App\\Handlers\\SlackEventHandler',
                    'method' => 'handleUrlVerification',
                ],
            ],
        ],

        'paypal' => [
            'display_name' => 'PayPal',
            'signature_validator' => 'provider',
            'algorithm' => 'sha256',
            'secret_key' => env('PAYPAL_WEBHOOK_SECRET'),
            'events' => [
                'PAYMENT.SALE.COMPLETED' => [
                    'handler' => 'App\\Handlers\\PayPalHandler',
                    'method' => 'handlePaymentCompleted',
                ],
                'PAYMENT.SALE.DENIED' => [
                    'handler' => 'App\\Handlers\\PayPalHandler',
                    'method' => 'handlePaymentDenied',
                ],
            ],
        ],

        // You can add more providers here as needed
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Event Types
    |--------------------------------------------------------------------------
    |
    | Define custom event types that your application can send out.
    | This serves as documentation and can be used for validation.
    |
    */
    'event_types' => [
        // Examples:
        'user.created' => [
            'description' => 'When a new user is created',
            'payload_schema' => [
                'id' => 'integer',
                'name' => 'string',
                'email' => 'string',
                'created_at' => 'datetime',
            ],
        ],
        'order.created' => [
            'description' => 'When a new order is created',
            'payload_schema' => [
                'id' => 'integer',
                'number' => 'string',
                'total' => 'decimal',
                'items' => 'array',
                'status' => 'string',
            ],
        ],
        // Add your own event types here
    ],
];
