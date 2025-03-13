<?php

use Illuminate\Http\Request;
use WizardingCode\WebhookOwlery\Validators\BasicAuthValidator;

it('validates correct basic auth credentials', function () {
    $validator = new BasicAuthValidator;

    $username = 'webhook';
    $password = 'secret';
    $credentials = base64_encode("$username:$password");

    $request = Request::create(
        '/webhook',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => "Basic $credentials"],
        '{"event":"test","data":{"key":"value"}}'
    );

    expect($validator->validate($request, "$username:$password"))->toBeTrue();
});

it('rejects incorrect basic auth credentials', function () {
    $validator = new BasicAuthValidator;

    $correctCreds = 'webhook:secret';
    $incorrectCreds = base64_encode('webhook:wrong');

    $request = Request::create(
        '/webhook',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => "Basic $incorrectCreds"],
        '{"event":"test","data":{"key":"value"}}'
    );

    expect($validator->validate($request, $correctCreds))->toBeFalse();
});

it('fails when authorization header is missing', function () {
    $validator = new BasicAuthValidator;

    $creds = 'webhook:secret';

    $request = Request::create(
        '/webhook',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json'], // No Authorization header
        '{"event":"test","data":{"key":"value"}}'
    );

    expect($validator->validate($request, $creds))->toBeFalse();
});

it('fails when authorization header is not Basic auth', function () {
    $validator = new BasicAuthValidator;

    $creds = 'webhook:secret';

    $request = Request::create(
        '/webhook',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer token'],
        '{"event":"test","data":{"key":"value"}}'
    );

    expect($validator->validate($request, $creds))->toBeFalse();
});

it('handles malformed basic auth headers', function () {
    $validator = new BasicAuthValidator;

    $creds = 'webhook:secret';
    $malformedCreds = 'not-base64-encoded';

    $request = Request::create(
        '/webhook',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => "Basic $malformedCreds"],
        '{"event":"test","data":{"key":"value"}}'
    );

    expect($validator->validate($request, $creds))->toBeFalse();
});

it('handles credentials without separator', function () {
    $validator = new BasicAuthValidator;

    $creds = 'webhook:secret';
    $badCreds = base64_encode('webhooksecret'); // No colon separator

    $request = Request::create(
        '/webhook',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => "Basic $badCreds"],
        '{"event":"test","data":{"key":"value"}}'
    );

    expect($validator->validate($request, $creds))->toBeFalse();
});
