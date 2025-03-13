<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Default Unit Testing Function
|--------------------------------------------------------------------------
|
| This function is automatically accessed by Pest, and it contains the logic
| you want to execute before your test suite is started. For completeness,
| we're adding PHPUnit annotations to use the Testbench package.
|
*/

function testCase(): TestCase
{
    return new \WizardingCode\WebhookOwlery\Tests\TestCase;
}