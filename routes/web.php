<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/admin', function () {
    return view('admin');
})->name('admin');

Route::get('/auth/install', [AuthController::class, 'install'])->name('auth.install');
Route::get('/auth/callback', [AuthController::class, 'callback'])->name('auth.callback');
