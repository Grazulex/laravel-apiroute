<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Tests\Support\Controllers;

use Illuminate\Http\JsonResponse;

final class PlainController
{
    public function index(): JsonResponse
    {
        return response()->json(['ok' => true]);
    }
}
