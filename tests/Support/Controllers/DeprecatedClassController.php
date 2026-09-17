<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Tests\Support\Controllers;

use Grazulex\ApiRoute\Attributes\Deprecated;
use Illuminate\Http\JsonResponse;

#[Deprecated(since: '2026-01-01', successor: '/api/v2/legacy', docs: 'https://docs.example.com/legacy')]
final class DeprecatedClassController
{
    public function index(): JsonResponse
    {
        return response()->json(['ok' => true]);
    }

    #[Deprecated(sunset: '2020-01-01', successor: 'api.v2.things.index', reason: 'Use things v2')]
    public function sunset(): JsonResponse
    {
        return response()->json(['ok' => true]);
    }
}
