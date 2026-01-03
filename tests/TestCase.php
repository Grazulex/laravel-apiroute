<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Tests;

use Grazulex\ApiRoute\ApiRouteServiceProvider;
use Grazulex\ApiRoute\Support\ApiVersionContext;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear the API version context between tests to avoid state leakage
        if ($this->app->bound(ApiVersionContext::class)) {
            $this->app->make(ApiVersionContext::class)->clear();
        }
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ApiRouteServiceProvider::class,
        ];
    }

    /**
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return [
            'ApiRoute' => \Grazulex\ApiRoute\Facades\ApiRoute::class,
        ];
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('apiroute.strategy', 'uri');
        $app['config']->set('apiroute.tracking.enabled', false);
    }
}
