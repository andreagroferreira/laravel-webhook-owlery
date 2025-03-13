<?php

use WizardingCode\WebhookOwlery\Facades\WebhookRepository;
use WizardingCode\WebhookOwlery\Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    // Create some test endpoints
    WebhookRepository::createEndpoint([
        'url' => 'https://example.com/webhook1',
        'identifier' => 'webhook1',
        'secret' => 'secret1',
        'description' => 'First test endpoint',
        'is_active' => true,
    ]);

    WebhookRepository::createEndpoint([
        'url' => 'https://example.com/webhook2',
        'identifier' => 'webhook2',
        'secret' => 'secret2',
        'description' => 'Second test endpoint',
        'is_active' => false,
    ]);
});

it('lists all webhook endpoints', function () {
    $this->artisan('webhook:list-endpoints')
        ->expectsTable(
            ['ID', 'Identifier', 'URL', 'Description', 'Status'],
            [
                [1, 'webhook1', 'https://example.com/webhook1', 'First test endpoint', 'Active'],
                [2, 'webhook2', 'https://example.com/webhook2', 'Second test endpoint', 'Inactive'],
            ]
        )
        ->assertExitCode(0);
});

it('filters endpoints by active status', function () {
    $this->artisan('webhook:list-endpoints', ['--active' => true])
        ->expectsTable(
            ['ID', 'Identifier', 'URL', 'Description', 'Status'],
            [
                [1, 'webhook1', 'https://example.com/webhook1', 'First test endpoint', 'Active'],
            ]
        )
        ->assertExitCode(0);
});

it('filters endpoints by inactive status', function () {
    $this->artisan('webhook:list-endpoints', ['--inactive' => true])
        ->expectsTable(
            ['ID', 'Identifier', 'URL', 'Description', 'Status'],
            [
                [2, 'webhook2', 'https://example.com/webhook2', 'Second test endpoint', 'Inactive'],
            ]
        )
        ->assertExitCode(0);
});
