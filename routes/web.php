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

Route::get('/db-check', function () {
    $connection = config('database.default');
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        \Illuminate\Support\Facades\DB::connection()->getDatabaseName();
        return response()->json([
            'ok' => true,
            'connection' => $connection,
            'database' => \Illuminate\Support\Facades\DB::connection()->getDatabaseName(),
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'ok' => false,
            'connection' => $connection,
            'error' => $e->getMessage(),
        ], 503);
    }
})->name('db-check');
