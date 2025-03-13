<?php

use WizardingCode\WebhookOwlery\Tests\TestCase;

uses(TestCase::class);

it('generates a webhook secret', function () {
    $this->artisan('webhook:generate-secret')
        ->expectsOutput('Generated webhook secret:')
        ->assertExitCode(0);
});

it('generates a secret with custom length', function () {
    $this->artisan('webhook:generate-secret', ['--length' => 32])
        ->expectsOutput('Generated webhook secret:')
        ->assertExitCode(0);
});

it('copies the secret to clipboard when requested', function () {
    // Mock the exec function to prevent actually copying to clipboard
    $this->mock('function_exists', function ($name) {
        return $name === 'proc_open';
    });

    $this->artisan('webhook:generate-secret', ['--copy' => true])
        ->expectsOutput('Generated webhook secret:')
        ->expectsOutput('Secret copied to clipboard!')
        ->assertExitCode(0);
});
