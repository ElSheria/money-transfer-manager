<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'name' => 'ABT-LACOLOMBE API',
    'status' => 'ready',
]));
