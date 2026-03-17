<?php

use App\Http\Controllers\Api\AdController;
use App\Http\Controllers\Api\adminController as AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FerryController;
use App\Http\Controllers\Api\GeneralController;
use App\Http\Controllers\Api\IslandController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RestaurantController;
use App\Http\Controllers\Api\RunnerController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\SupportMessageController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::group(['controller' => CheckoutController::class, 'prefix' => 'checkout', 'middleware' => 'auth:api'], function () {
    Route::post('/create-payment-intent', 'createPaymentIntent');
    Route::post('/confirm-payment', 'confirmPayment');
});

Route::group(['controller' => GeneralController::class, 'prefix' => 'pages'], function () {
    Route::get('/{title}', 'getPage');
    Route::post('/', 'createPage')->middleware(['auth:api', 'role.admin']);
    Route::put('/{id}', 'updatePage')->middleware(['auth:api', 'role.admin']);
    Route::delete('/{id}', 'deletePage')->middleware(['auth:api', 'role.admin']);
});

Route::group(['controller' => GeneralController::class, 'prefix' => 'faqs'], function () {
    Route::get('/', 'getFaqs');
    Route::get('/get-all', 'getAllFaqs')->middleware(['auth:api', 'role.admin']);
    Route::get('/details/{id}', 'faqDetails')->middleware(['auth:api', 'role.admin']);
    Route::post('/create', 'createFaq')->middleware(['auth:api', 'role.admin']);
    Route::put('/update/{id}', 'updateFaq')->middleware(['auth:api', 'role.admin']);
    Route::delete('/delete/{id}', 'deleteFaq')->middleware(['auth:api', 'role.admin']);
    Route::post('/upsert', 'upsertFaq')->middleware(['auth:api', 'role.admin']);
    Route::delete('/{id}', 'deleteFaq')->middleware(['auth:api', 'role.admin']);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::group(['controller' => AuthController::class, 'prefix' => 'auth'], function () {
    Route::post('/register', 'register');
    Route::post('/verify-otp', 'verifyEmail');
    Route::post('/resend-otp', 'resendOtp');
    Route::post('/forgot-password', 'forgotPassword');
    Route::post('/login', 'login');
    Route::post('/logout', 'logout');

    // Protected routes — require JWT token (e.g. from OTP verification)
    Route::middleware('auth:api')->group(function () {
        Route::post('/reset-password', 'resetPassword');
        Route::post('/change-password', 'changePassword');
    });
});

Route::group(['controller' => ProfileController::class, 'prefix' => 'profile', 'middleware' => 'auth:api'], function () {
    Route::get('/', 'viewProfile');
    Route::post('/update-profile', 'updateProfile');
    Route::post('/update-avatar', 'updateAvatar');
    Route::delete('/delete-account', 'deleteAccount');
});

Route::group(['controller' => NotificationController::class, 'prefix' => 'notifications', 'middleware' => 'auth:api'], function () {
    Route::get('/', 'notifications');
    Route::get('/unread-count', 'getUnreadCount');
    Route::put('/mark-as-read/{id}', 'markAsRead');
    Route::put('/mark-all-as-read', 'readAll');
    Route::delete('/delete/{id}', 'delete');
});

Route::group(['controller' => IslandController::class, 'prefix' => 'island', 'middleware' => 'auth:api'], function () {
    Route::post('/create', 'create')->middleware('role.admin');
    Route::get('/get-all', 'getAll');
    Route::put('/update/{id}', 'update')->middleware('role.admin');
    Route::delete('/delete/{id}', 'delete')->middleware('role.admin');
    Route::get('/details/{id}', 'details');
    Route::get('/ferries/{id}', 'ferries');
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
    Route::put('/cancel/{id}', 'cancel')->middleware('role.user');
    Route::post('/approve-delivery/{id}', 'approveDelivery')->middleware('role.user');
    Route::post('/reject-delivery/{id}', 'rejectDelivery')->middleware('role.user');
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
    Route::post('/approve/{id}', 'approveTask');
});

Route::group(['controller' => MessageController::class, 'prefix' => 'messages', 'middleware' => 'auth:api'], function () {
    Route::post('send', 'sendMessage');
    Route::get('get-messages/{userId}', 'getMessages');
    Route::put('read/{senderId}', 'markAsRead');
});

Route::group(['controller' => SupportMessageController::class, 'prefix' => 'support-messages', 'middleware' => 'auth:api'], function () {
    Route::post('/send', 'send');
    Route::get('/my-messages', 'myMessages');
    Route::delete('/delete/{id}', 'delete');
    Route::get('/admin/get-all', 'adminGetAll')->middleware('role.admin');
    Route::get('/admin/details/{id}', 'adminDetails')->middleware('role.admin');
    Route::post('/admin/reply/{id}', 'adminReply')->middleware('role.admin');

});

Route::group(['controller' => RunnerController::class, 'prefix' => 'runner', 'middleware' => 'auth:api'], function () {
    Route::get('/list', 'runnersList');
    Route::get('/get-all', 'getAll');
    Route::get('/details/{id}', 'details');
    Route::post('/create', 'create')->middleware('role.admin');
    Route::put('/update/{id}', 'updateRunner')->middleware('role.admin');
    Route::delete('/delete/{id}', 'delete')->middleware('role.admin');
    Route::post('/accept-order/{order_id}', 'acceptOrder')->middleware('role.runner');
    Route::post('/decline-order/{order_id}', 'declineOrder')->middleware('role.runner');
    Route::post('/order-completed/{order_id}', 'orderCompleted')->middleware('role.runner');
});

Route::group(['controller' => UserController::class, 'prefix' => 'user', 'middleware' => 'auth:api'], function () {
    Route::get('/list', 'usersList')->middleware('role.admin');
    Route::get('/lost-users', 'lostUsers')->middleware('role.admin');
    Route::get('/details/{id}', 'details');
    Route::delete('/delete/{id}', 'delete')->middleware('role.admin');
});

Route::group(['controller' => AdminController::class, 'prefix' => 'admin', 'middleware' => ['auth:api', 'role.admin']], function () {
    Route::post('/accept-and-assign/{order_id}/{runner_user_id}', 'acceptAndAssign');
    Route::post('/request-delivery/{order_id}', 'requestDelivery');
});

Route::group(['controller' => SubscriptionController::class, 'prefix' => 'subscription', 'middleware' => 'auth:api'], function () {
    Route::get('/plan', 'plan');
    Route::post('/subscribe', 'subscribe');
    Route::get('/status', 'status');
    Route::get('/billing-portal', 'billingPortal');
    Route::post('/cancel', 'cancel');
    Route::post('/resume', 'resume');
    Route::get('/invoices', 'invoices');
    Route::get('/upcoming-invoice', 'upcomingInvoice');
    Route::get('/invoice/{invoiceId}/download', 'downloadInvoice');
});

Route::group(['controller' => DashboardController::class, 'prefix' => 'dashboard', 'middleware' => ['auth:api', 'role.admin']], function () {
    Route::get('/overview', 'overview');
    Route::get('/registration-statistics', 'registrationStatistics');
    Route::get('/weekly-task-service-statistics', 'weeklyTaskServiceStatistics');
});
