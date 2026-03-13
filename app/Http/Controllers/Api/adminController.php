<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Order\AcceptAndAssignOrderRequest;
use App\Models\Order;
use App\Models\User;
use App\Traits\ApiResponseTraits;
use App\Traits\NotificationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class adminController extends Controller
{
    use ApiResponseTraits;
    use NotificationTrait;

    public function acceptAndAssign(AcceptAndAssignOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $order = DB::transaction(function () use ($validated) {
            $order = Order::query()->findOrFail($validated['order_id']);

            if (in_array($order->admin_status, ['completed', 'cancelled'], true)) {
                throw ValidationException::withMessages([
                    'order_id' => 'This order can no longer be assigned.',
                ]);
            }

            $runner = User::query()
                ->where('id', $validated['runner_user_id'])
                ->firstOrFail();

            $order->update([
                'admin_status' => 'pending',
                'user_status' => 'pending',
                'runner_status' => 'new',
                'runner_id' => $runner->id,
                'delivery_requested' => false,
            ]);

            return $order;
        });

        $this->notifyUser(
            $order->runner_id,
            'New Order Assigned',
            "You have been assigned to order #{$order->id}. Please review and accept or decline.",
            'order_assigned',
            $order->id
        );

        return $this->successResponse(
            $order->load(['user', 'foodDelivery', 'ferryDrop']),
            'Order accepted and assigned successfully.',
            200
        );
    }

    public function requestDelivery($order_id): JsonResponse
    {
        $order = Order::query()->findOrFail($order_id);

        if ($order->runner_status !== 'completed' || ! $order->delivery_requested) {
            return $this->errorResponse('This order is not ready for delivery confirmation request.', 422);
        }

        if ($order->user_status === 'pending_approval') {
            return $this->errorResponse('Delivery confirmation has already been requested.', 422);
        }

        DB::transaction(function () use ($order) {
            $order->update([
                'user_status' => 'pending_approval',
            ]);
        });

        $order->refresh();

        $this->notifyUser(
            $order->user_id,
            'Confirm Delivery',
            "Please confirm the delivery for order #{$order->id}.",
            'delivery_confirmation_request',
            $order->id
        );

        return $this->successResponse(
            $order->load(['user', 'foodDelivery', 'ferryDrop']),
            'Delivery confirmation requested.',
            200
        );
    }
}
