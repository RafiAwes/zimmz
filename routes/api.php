<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\IslandController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::group(['controller' => AuthController::class, 'prefix' => 'auth'], function () {
    Route::post('/register', 'register');
    Route::post('/verify-email', 'verifyEmail');
    Route::post('/resend-otp', 'resendOtp');
    Route::post('/forgot-password', 'forgotPassword');
    Route::post('/reset-password', 'resetPassword');
    Route::post('/change-password', 'changePassword');
    Route::post('/login', 'login');
    Route::post('/logout', 'logout');
});

Route::group(['controller' => ProfileController::class, 'prefix' => 'profile', 'middleware' => 'auth:api'], function () {
    Route::post('/update-profile', 'updateProfile');
    Route::post('/update-avatar', 'updateAvatar');
});

Route::group(['controller' => IslandController::class, 'prefix' => 'island', 'middleware' => 'auth:api'], function () {
    Route::post('/create', 'create')->middleware('role.admin');
    Route::get('/get-all', 'getAll');
    Route::put('/update/{id}', 'update')->middleware('role.admin');
    Route::delete('/delete/{id}', 'delete')->middleware('role.admin');
});
