<?php

use Illuminate\Http\Request;
use WizardingCode\WebhookOwlery\Validators\HmacSignatureValidator;

it('validates correct HMAC signatures', function () {
    $validator = new HmacSignatureValidator;

    $secret = 'test-secret';
    $payload = '{"event":"test","data":{"key":"value"}}';
    $signature = hash_hmac('sha256', $payload, $secret);

    $request = new Request(
        [], // GET
        [], // POST
        [], // Attributes
        [], // Cookies
        [], // Files
        ['HTTP_X_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], // Server vars
        $payload // Content
    );

    expect($validator->validate($request, $secret))->toBeTrue();
});

it('rejects incorrect HMAC signatures', function () {
    $validator = new HmacSignatureValidator;

    $secret = 'test-secret';
    $payload = '{"event":"test","data":{"key":"value"}}';
    $incorrectSignature = 'incorrect-signature';

    $request = new Request(
        [], // GET
        [], // POST
        [], // Attributes
        [], // Cookies
        [], // Files
        ['HTTP_X_SIGNATURE' => $incorrectSignature, 'CONTENT_TYPE' => 'application/json'], // Server vars
        $payload // Content
    );

    expect($validator->validate($request, $secret))->toBeFalse();
});

it('can use a custom header name', function () {
    $validator = new HmacSignatureValidator('X-Custom-Signature');

    $secret = 'test-secret';
    $payload = '{"event":"test","data":{"key":"value"}}';
    $signature = hash_hmac('sha256', $payload, $secret);

    $request = new Request(
        [], // GET
        [], // POST
        [], // Attributes
        [], // Cookies
        [], // Files
        ['HTTP_X_CUSTOM_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], // Server vars
        $payload // Content
    );

    expect($validator->validate($request, $secret))->toBeTrue();
});

it('can use a custom algorithm', function () {
    $validator = new HmacSignatureValidator('X-Signature', 'sha512');

    $secret = 'test-secret';
    $payload = '{"event":"test","data":{"key":"value"}}';
    $signature = hash_hmac('sha512', $payload, $secret);

    $request = new Request(
        [], // GET
        [], // POST
        [], // Attributes
        [], // Cookies
        [], // Files
        ['HTTP_X_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], // Server vars
        $payload // Content
    );

    expect($validator->validate($request, $secret))->toBeTrue();
});
