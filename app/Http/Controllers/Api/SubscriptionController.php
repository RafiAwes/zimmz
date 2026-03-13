<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTraits;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubscriptionController extends Controller
{
    use ApiResponseTraits;

    public function subscribe(Request $request)
    {
        $request->validate([
            'price_id' => 'required|string',
        ]);

        $user = Auth::user();

        if ($user->subscribed('default')) {
            return $this->errorResponse('You are already subscribed.', 400);
        }

        $checkout = $user->newSubscription('default', $request->price_id)->checkout([
            'success_url' => config('app.frontend_url').'/payment/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => config('app.frontend_url').'/payment/cancel',
        ]);

        return $this->successResponse($checkout, 'Subscription checkout created successfully.', 201);
    }

    public function status()
    {
        $user = Auth::user();

        if ($user->subscribed('default')) {
            return $this->successResponse(['status' => 'active'], 'Subscription is active.');
        }

        return $this->successResponse(['status' => 'inactive'], 'No active subscription found.');
    }
}
