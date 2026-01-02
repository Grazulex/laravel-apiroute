<?php

use Illuminate\Support\Facades\Route;

Route::get('test', fn () => response()->json(['ok' => true, 'version' => 'v1']));
Route::get('users', fn () => response()->json(['users' => []]));
