<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Order\AcceptAndAssignOrderRequest;
use App\Models\Order;
use App\Models\Runner;
use App\Traits\ApiResponseTraits;
use App\Traits\NotificationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class adminController extends Controller
{
    use ApiResponseTraits;
    use NotificationTrait;

    public function acceptAndAssign(AcceptAndAssignOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $order = DB::transaction(function () use ($validated) {
            $order = Order::query()->findOrFail($validated['order_id']);

            $runner = Runner::query()
                ->where('user_id', $validated['runner_user_id'])
                ->firstOrFail();

            $order->update([
                'runner_id' => $runner->id,
                'runner_status' => 'pending',
            ]);

            return $order;
        });

        // Notify the assigned runner
        $this->notifyUser(
            $order->runner->user_id,
            'New Order Assigned',
            "You have been assigned to order #{$order->id}. Please review and accept or decline.",
            'order_assigned',
            $order->id
        );

        return $this->successResponse(
            $order->load(['user', 'runner.user', 'foodDelivery', 'ferryDrop']),
            'Order accepted and assigned successfully.',
            200
        );
    }
}
