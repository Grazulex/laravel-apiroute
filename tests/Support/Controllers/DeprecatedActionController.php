<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Tests\Support\Controllers;

use Grazulex\ApiRoute\Attributes\Deprecated;
use Illuminate\Http\JsonResponse;

final class DeprecatedActionController
{
    public static int $reflections = 0;

    #[Deprecated(since: '2026-03-01', sunset: '2099-01-01', successor: 'https://api.example.com/v2/items')]
    public function deprecated(): JsonResponse
    {
        return response()->json(['ok' => true]);
    }

    public function fresh(): JsonResponse
    {
        return response()->json(['ok' => true]);
    }
}
