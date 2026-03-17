<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\TaskService;
use App\Models\Transaction;
use App\Traits\ApiResponseTraits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Cashier\Cashier;
use Stripe\PaymentIntent;
use Throwable;

class CheckoutController extends Controller
{
    use ApiResponseTraits;

    public function createPaymentIntent(Request $request): JsonResponse
    {
        $request->validate([
            'id' => 'required|integer', // order or task id
            'type' => 'required|string|in:order,task_service',
        ]);

        $user = Auth::guard('api')->user();
        $id = $request->input('id');
        $type = $request->input('type');

        try {
            $payable = $type === 'order'
                ? Order::findOrFail($id)
                : TaskService::findOrFail($id);

            // Basic authorization check
            if ((int) $payable->user_id !== (int) $user->id) {
                return $this->errorResponse('You are not authorized to pay for this.', 403);
            }

            $amount = $type === 'order' ? $payable->total_cost : $payable->price;

            // Amount in cents for Stripe
            $amountInCents = (int) ($amount * 100);

            if ($amountInCents <= 0) {
                return $this->errorResponse('Invalid amount for payment.', 422);
            }

            /** @var PaymentIntent $intent */
            $intent = Cashier::stripe()->paymentIntents->create([
                'amount' => $amountInCents,
                'currency' => 'usd',
                'capture_method' => 'manual',
                'automatic_payment_methods' => [
                    'enabled' => true,
                    'allow_redirects' => 'never',
                ],
                'metadata' => [
                    'user_id' => $user->id,
                    'payable_id' => $payable->id,
                    'payable_type' => get_class($payable),
                ],
            ]);

            Transaction::create([
                'user_id' => $user->id,
                'payable_type' => get_class($payable),
                'payable_id' => $payable->id,
                'amount' => $amount,
                'currency' => 'usd',
                'payment_intent_id' => $intent->id,
                'status' => 'pending',
                'payment_method' => 'stripe',
                'payload' => $intent->toArray(),
            ]);

            return $this->successResponse([
                'client_secret' => $intent->client_secret,
                'payment_intent_id' => $intent->id,
            ], 'Payment intent created successfully.', 201);

        } catch (Throwable $e) {
            return $this->errorResponse('Failed to create payment intent: '.$e->getMessage(), 500);
        }
    }

    public function confirmPayment(Request $request): JsonResponse
    {
        $request->validate([
            'payment_intent_id' => 'required|string',
            'payment_method' => 'nullable|string', // Optional: for manual testing in Postman
        ]);

        $paymentIntentId = $request->input('payment_intent_id');
        $paymentMethod = $request->input('payment_method');

        try {
            $stripe = Cashier::stripe();
            /** @var PaymentIntent $intent */
            $intent = $stripe->paymentIntents->retrieve($paymentIntentId);

            // If it's still waiting for a payment method and we provided one (e.g. pm_card_visa), confirm it now
            if ($intent->status === 'requires_payment_method' && $paymentMethod) {
                $intent = $stripe->paymentIntents->confirm($paymentIntentId, [
                    'payment_method' => $paymentMethod,
                ]);
            }

            $transaction = Transaction::where('payment_intent_id', $paymentIntentId)->firstOrFail();

            if ($intent->status === 'requires_capture' || $intent->status === 'succeeded') {
                $status = $intent->status === 'requires_capture' ? 'authorized' : 'paid';
                $transaction->update([
                    'status' => $status,
                    'payload' => $intent->toArray(),
                ]);

                return $this->successResponse($transaction, 'Payment processed successfully. Current status: '.$status, 200);
            }

            return $this->errorResponse('Payment not in a successful state. Current status: '.$intent->status, 422);

        } catch (Throwable $e) {
            return $this->errorResponse('Failed to confirm payment: '.$e->getMessage(), 500);
        }
    }

    public function capturePayment(string $paymentIntentId): bool
    {
        try {
            /** @var PaymentIntent $intent */
            $intent = Cashier::stripe()->paymentIntents->capture($paymentIntentId);

            $transaction = Transaction::where('payment_intent_id', $paymentIntentId)->first();

            if ($transaction && $intent->status === 'succeeded') {
                $transaction->update([
                    'status' => 'paid',
                    'payload' => $intent->toArray(),
                ]);

                return true;
            }
        } catch (Throwable $e) {
            // Log or handle error
        }

        return false;
    }
}
