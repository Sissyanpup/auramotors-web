<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Send every request with the SPA's Referer so Sanctum's
     * EnsureFrontendRequestsAreStateful treats it as a stateful frontend request,
     * matching how the real Next.js frontend calls the API.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Referer', config('app.frontend_url', 'http://localhost:3000'));
    }
}
