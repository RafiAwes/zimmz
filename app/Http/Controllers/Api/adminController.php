<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Order\AcceptAndAssignOrderRequest;
use App\Models\Order;
use App\Models\Runner;
use App\Traits\ApiResponseTraits;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class adminController extends Controller
{
    use ApiResponseTraits;

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

        return $this->successResponse(
            $order->load(['user', 'runner.user', 'foodDelivery', 'ferryDrop']),
            'Order accepted and assigned successfully.',
            200
        );
    }
}
