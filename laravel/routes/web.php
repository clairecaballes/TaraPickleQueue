<?php

use Illuminate\Support\Facades\Route;

// Single-page application shell — Vue Router (history mode) handles the rest.
// The health check (/up) is registered by bootstrap/app.php before this
// wildcard, so it keeps matching.
Route::get('/{any}', fn () => view('app'))->where('any', '.*');
