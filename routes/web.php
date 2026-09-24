<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ConnectorOAuthController;
use App\Support\UiMode;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — Okyema (PHP-rendered Vue.js UI, no Blade)
|--------------------------------------------------------------------------
*/

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Google OAuth
Route::get('/auth/google/redirect', [AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);

// Calendar connector OAuth (add/connect a calendar). Sign-in is a separate client.
Route::middleware('auth')->group(function () {
    Route::get('/connectors/{provider}/redirect', [ConnectorOAuthController::class, 'redirect'])
        ->whereIn('provider', ['google', 'microsoft', 'notion']);
    Route::get('/connectors/{provider}/callback', [ConnectorOAuthController::class, 'callback'])
        ->whereIn('provider', ['google', 'microsoft', 'notion']);
});

// The app shell. Guests go straight to login; signed-in users get the SPA.
Route::get('/', function () {
    if (! auth()->check()) {
        return redirect('/login');
    }

    $base = rtrim(request()->getBasePath(), '/');
    $connectorNotice = session()->pull('connector_notice');
    $view = UiMode::isSimple() ? 'simple.php' : 'app.php';
    $__path = resource_path('views/'.$view);
    extract(['base' => $base, 'connectorNotice' => $connectorNotice]);
    ob_start();
    include $__path;

    return response(ob_get_clean());
})->name('dashboard');
