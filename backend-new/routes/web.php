<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| This file is required by backend-new/bootstrap/app.php via:
|   withRouting(web: __DIR__.'/../routes/web.php')
|
*/

Route::get('/', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'Welcome to the logistics API (web routes).',
    ]);
});
