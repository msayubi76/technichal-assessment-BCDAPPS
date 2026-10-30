<?php

use Illuminate\Support\Facades\Route;

// This backend only serves the API. The rider app lives in the frontend folder.
Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'api' => url('/api'),
    'health' => url('/api/health'),
]));
