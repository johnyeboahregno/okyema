<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthController;
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

// The app shell. Guests land on the splash; signed-in users get the SPA.
Route::get('/', function () {
    $base = rtrim(request()->getBasePath(), '/');
    $view = auth()->check() ? 'app' : 'welcome';
    $__path = resource_path("views/{$view}.php");
    extract(['base' => $base]);
    ob_start();
    include $__path;

    return response(ob_get_clean());
})->name('dashboard');
