<?php

use App\Http\Controllers\Api\AdController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FerryController;
use App\Http\Controllers\Api\GeneralController;
use App\Http\Controllers\Api\IslandController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RestaurantController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::group(['controller' => GeneralController::class, 'prefix' => 'pages'], function () {
    Route::get('/{title}', 'getPage');
    Route::post('/', 'createPage')->middleware(['auth:api', 'role.admin']);
    Route::put('/{id}', 'updatePage')->middleware(['auth:api', 'role.admin']);
    Route::delete('/{id}', 'deletePage')->middleware(['auth:api', 'role.admin']);
});

Route::group(['controller' => GeneralController::class, 'prefix' => 'faqs'], function () {
    Route::get('/', 'getFaqs');
    Route::post('/upsert', 'upsertFaq')->middleware(['auth:api', 'role.admin']);
    Route::delete('/{id}', 'deleteFaq')->middleware(['auth:api', 'role.admin']);
});

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
    Route::get('/details/{id}', 'details');
});

Route::group(['controller' => FerryController::class, 'prefix' => 'ferry', 'middleware' => 'auth:api'], function () {
    Route::post('/create', 'create')->middleware('role.admin');
    Route::get('/get-all', 'getAll');
    Route::put('/update/{id}', 'update')->middleware('role.admin');
    Route::delete('/delete/{id}', 'delete')->middleware('role.admin');
    Route::get('/details/{id}', 'details');
});

Route::group(['controller' => RestaurantController::class, 'prefix' => 'restaurant', 'middleware' => 'auth:api'], function () {
    Route::post('/create', 'create')->middleware('role.admin');
    Route::get('/get-all', 'getAll');
    Route::get('/details/{id}', 'details');
    Route::put('/update/{id}', 'update')->middleware('role.admin');
    Route::delete('/delete/{id}', 'delete')->middleware('role.admin');
});

Route::group(['controller' => RestaurantController::class, 'prefix' => 'restaurant', 'middleware' => 'auth:api'], function () {
    Route::post('/create', 'create')->middleware('role.admin');
    Route::get('/get-all', 'getAll');
    Route::get('/details/{id}', 'details');
    Route::put('/update/{id}', 'update')->middleware('role.admin');
    Route::delete('/delete/{id}', 'delete')->middleware('role.admin');
});

Route::group(['controller' => AdController::class, 'prefix' => 'ad', 'middleware' => 'auth:api'], function () {
    Route::post('/create', 'create')->middleware('role.admin');
    Route::get('/get-all', 'getAll');
    Route::get('/details/{id}', 'details');
    Route::put('/update/{id}', 'update')->middleware('role.admin');
    Route::delete('/delete/{id}', 'delete')->middleware('role.admin');
});

Route::group(['controller' => OrderController::class, 'prefix' => 'order', 'middleware' => 'auth:api'], function () {
    Route::post('/create', 'create');
    Route::get('/get-all', 'getAll');
    Route::get('/details/{id}', 'details');
    Route::put('/update/{id}', 'update');
    Route::delete('/delete/{id}', 'delete');
});

Route::group(['controller' => TaskController::class, 'prefix' => 'task-service', 'middleware' => 'auth:api'], function () {
    Route::get('/', 'getAll');
    Route::get('/my-tasks', 'getMyTasks');
    Route::get('/details/{id}', 'details');
    Route::post('/create', 'create');
    Route::put('/update/{id}', 'update');
    Route::delete('/delete/{id}', 'delete');
    Route::post('/accept/{id}', 'runnerAcceptTask');
    Route::post('/reject/{id}', 'runnerRejectTask');
    Route::post('/complete/{id}', 'runnerCompleteTask');
});

Route::group(['controller' => MessageController::class, 'prefix' => 'messages', 'middleware' => 'auth:api'], function () {
    Route::post('send', 'sendMessage');
    Route::get('get-messages/{userId}', 'getMessages');
    Route::put('read/{senderId}', 'markAsRead');
});
