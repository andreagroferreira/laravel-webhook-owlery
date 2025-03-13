<?php

use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use WizardingCode\WebhookOwlery\Validators\JwtSignatureValidator;

beforeEach(function () {
    // Skip tests if the JWT library is not available
    if (! class_exists(JWT::class)) {
        $this->markTestSkipped('JWT library not installed');
    }
});

it('validates correct JWT tokens', function () {
    $validator = new JwtSignatureValidator;

    $secret = 'test-secret';
    $payload = '{"event":"test","data":{"key":"value"}}';

    // Create a JWT token
    $token = JWT::encode([
        'iat' => time(),
        'exp' => time() + 3600,
        'payload' => base64_encode($payload),
    ], $secret, 'HS256');

    $request = Request::create(
        '/webhook',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => "Bearer $token"],
        $payload
    );

    expect($validator->validate($request, $secret))->toBeTrue();
});

it('rejects invalid JWT tokens', function () {
    $validator = new JwtSignatureValidator;

    $secret = 'test-secret';
    $payload = '{"event":"test","data":{"key":"value"}}';
    $invalidToken = 'invalid.token.format';

    $request = Request::create(
        '/webhook',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => "Bearer $invalidToken"],
        $payload
    );

    expect($validator->validate($request, $secret))->toBeFalse();
});

it('rejects expired JWT tokens', function () {
    $validator = new JwtSignatureValidator;

    $secret = 'test-secret';
    $payload = '{"event":"test","data":{"key":"value"}}';

    // Create an expired JWT token
    $token = JWT::encode([
        'iat' => time() - 7200,
        'exp' => time() - 3600, // Expired 1 hour ago
        'payload' => base64_encode($payload),
    ], $secret, 'HS256');

    $request = Request::create(
        '/webhook',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => "Bearer $token"],
        $payload
    );

    expect($validator->validate($request, $secret))->toBeFalse();
});

it('rejects tokens with mismatched payloads', function () {
    $validator = new JwtSignatureValidator;

    $secret = 'test-secret';
    $originalPayload = '{"event":"original","data":{"key":"original"}}';
    $actualPayload = '{"event":"modified","data":{"key":"modified"}}';

    // Create a JWT token with the original payload
    $token = JWT::encode([
        'iat' => time(),
        'exp' => time() + 3600,
        'payload' => base64_encode($originalPayload),
    ], $secret, 'HS256');

    // But send a different payload in the request
    $request = Request::create(
        '/webhook',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => "Bearer $token"],
        $actualPayload
    );

    expect($validator->validate($request, $secret))->toBeFalse();
});
