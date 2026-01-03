<?php

declare(strict_types=1);

use Carbon\Carbon;
use Grazulex\ApiRoute\Facades\ApiRoute;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Tests for API version headers on error responses.
 *
 * These tests verify that X-API-Version and X-API-Version-Status headers
 * are added to ALL responses, including error responses (401, 403, 404, 500, etc.).
 *
 * @see https://github.com/Grazulex/laravel-apiroute/issues/16
 */
test('adds version headers to 401 unauthorized response', function () {
    ApiRoute::version('v1', function () {
        Route::get('protected', function () {
            throw new UnauthorizedHttpException('Bearer', 'Unauthenticated.');
        });
    });

    $response = $this->get('/api/v1/protected');

    $response->assertUnauthorized();
    $response->assertHeader('X-API-Version', 'v1');
    $response->assertHeader('X-API-Version-Status', 'active');
});

test('adds version headers to 403 forbidden response', function () {
    ApiRoute::version('v1', function () {
        Route::get('forbidden', function () {
            throw new AccessDeniedHttpException('Access denied.');
        });
    });

    $response = $this->get('/api/v1/forbidden');

    $response->assertForbidden();
    $response->assertHeader('X-API-Version', 'v1');
    $response->assertHeader('X-API-Version-Status', 'active');
});

test('adds version headers to 404 not found response', function () {
    ApiRoute::version('v1', function () {
        Route::get('missing', function () {
            throw new NotFoundHttpException('Resource not found.');
        });
    });

    $response = $this->get('/api/v1/missing');

    $response->assertNotFound();
    $response->assertHeader('X-API-Version', 'v1');
    $response->assertHeader('X-API-Version-Status', 'active');
});

test('adds version headers to 500 server error response', function () {
    ApiRoute::version('v1', function () {
        Route::get('error', function () {
            throw new \RuntimeException('Internal server error.');
        });
    });

    $response = $this->get('/api/v1/error');

    $response->assertServerError();
    $response->assertHeader('X-API-Version', 'v1');
    $response->assertHeader('X-API-Version-Status', 'active');
});

test('adds version headers to 410 sunset response', function () {
    ApiRoute::version('v1', function () {
        Route::get('test', fn () => response()->json(['ok' => true]));
    })->sunset(Carbon::now()->subDay());

    $response = $this->get('/api/v1/test');

    $response->assertStatus(410);
    $response->assertHeader('X-API-Version', 'v1');
    $response->assertHeader('X-API-Version-Status', 'sunset');
});

test('adds deprecation headers to error response for deprecated version', function () {
    ApiRoute::version('v1', function () {
        Route::get('error', function () {
            throw new NotFoundHttpException('Not found.');
        });
    })->deprecated('2025-06-01');

    $response = $this->get('/api/v1/error');

    $response->assertNotFound();
    $response->assertHeader('X-API-Version', 'v1');
    $response->assertHeader('X-API-Version-Status', 'deprecated');
    $response->assertHeader('Deprecation');
});

test('adds sunset header to error response when sunset date is set', function () {
    ApiRoute::version('v1', function () {
        Route::get('error', function () {
            throw new NotFoundHttpException('Not found.');
        });
    })->deprecated('2025-06-01')->sunset('2099-12-01');

    $response = $this->get('/api/v1/error');

    $response->assertNotFound();
    $response->assertHeader('X-API-Version', 'v1');
    $response->assertHeader('Sunset');
});

test('adds successor link header to error response', function () {
    ApiRoute::version('v1', function () {
        Route::get('error', function () {
            throw new NotFoundHttpException('Not found.');
        });
    })->deprecated('2025-06-01')->setSuccessor('v2');

    $response = $this->get('/api/v1/error');

    $response->assertNotFound();
    $response->assertHeader('Link');
    expect($response->headers->get('Link'))->toContain('successor-version');
});

test('version headers can be disabled for error responses', function () {
    config(['apiroute.headers.enabled' => false]);

    ApiRoute::version('v1', function () {
        Route::get('error', function () {
            throw new NotFoundHttpException('Not found.');
        });
    });

    $response = $this->get('/api/v1/error');

    $response->assertNotFound();
    expect($response->headers->has('X-API-Version'))->toBeFalse();
    expect($response->headers->has('X-API-Version-Status'))->toBeFalse();
});

test('does not add headers when version is not resolved', function () {
    // Request to a non-existent version - VersionNotFoundException is thrown
    // before the version can be stored in the context
    ApiRoute::version('v1', function () {
        Route::get('test', fn () => response()->json(['ok' => true]));
    });

    $response = $this->get('/api/v999/test');

    $response->assertNotFound();
    // Headers should NOT be present because the version was never resolved
    expect($response->headers->has('X-API-Version'))->toBeFalse();
});
