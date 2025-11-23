<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Disable CSRF protection for all tests
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        // Disable LogActivity middleware during tests
        $this->withoutMiddleware(\App\Http\Middleware\LogActivity::class);
    }
}
