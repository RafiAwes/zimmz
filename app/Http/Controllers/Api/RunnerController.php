<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRunnerRequest;
use App\Models\Order;
use App\Models\Runner;
use App\Models\User;
use App\Traits\ApiResponseTraits;
use App\Traits\NotificationTrait;
use App\Traits\OrderStatusTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RunnerController extends Controller
{
    use ApiResponseTraits;
    use NotificationTrait;
    use OrderStatusTrait;

    public function runnersList(Request $request)
    {
        return $this->getAll($request);
    }

    public function create(StoreRunnerRequest $request)
    {
        $validated = $request->validated();

        try {
            return DB::transaction(function () use ($validated) {
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'contact_number' => $validated['phone'] ?? null,
                    'address' => $validated['location'] ?? null,
                    'role' => 'runner',
                    'email_verified_at' => now(), // Default to verified since created by admin
                    'is_active' => true,
                ]);

                $runner = Runner::create([
                    'user_id' => $user->id,
                    'category' => $validated['runner_category'],
                    'type' => $validated['runner_type'] ?? 'assigned',
                ]);

                return $this->successResponse($user->load('runner'), 'Runner created successfully.', 201);
            });
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create runner.', 500, $e->getMessage());
        }
    }

    public function getAll(Request $request)
    {
        $per_page = $request->per_page ?? 5;
        $search = $request->search;
        $type = $request->type;
        $category = $request->category;

        $runners = User::query()
            ->where('role', 'runner')
            ->with('runner')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('contact_number', 'like', "%{$search}%");
                });
            })
            ->when($type, function ($query, $type) {
                $query->whereHas('runner', function ($q) use ($type) {
                    $q->where('type', $type);
                });
            })
            ->when($category, function ($query, $category) {
                $query->whereHas('runner', function ($q) use ($category) {
                    $q->where('category', $category);
                });
            })
            ->paginate($per_page);

        return $this->successResponse($runners, 'Runners fetched successfully.', 200);
    }

    public function details($id)
    {
        $runner = User::where('role', 'runner')->with('runner')->findOrFail($id);

        return $this->successResponse($runner, 'Runner details fetched successfully.', 200);
    }

    public function acceptOrder($order_id): JsonResponse
    {
        $runnerUser = Auth::guard('api')->user();
        $runner = $runnerUser?->runner;

        if (! $runnerUser || ! $runner) {
            return $this->errorResponse('Runner profile is required to accept orders.', 403);
        }

        $order = Order::query()->findOrFail($order_id);

        if ((int) $order->runner_id !== (int) $runner->id) {
            return $this->errorResponse('This order is not assigned to you.', 403);
        }

        if ($order->status !== 'pending' || $order->runner_status !== 'pending') {
            return $this->errorResponse('Only newly assigned orders can be accepted.', 422);
        }

        DB::transaction(function () use ($order) {
            $order->update([
                'status' => 'pending',
                'runner_status' => 'assigned',
            ]);
        });

        $order->refresh()->load(['user', 'runner.user', 'foodDelivery', 'ferryDrop']);

        $this->notifyAdmins(
            'Order Accepted by Runner',
            "Order #{$order->id} has been accepted by runner {$runnerUser->name}.",
            'order_accepted',
            $order->id
        );

        $this->notifyUser(
            $order->user_id,
            'Runner Accepted Your Order',
            "Your order #{$order->id} has been accepted by a runner and is on the way!",
            'order_accepted',
            $order->id
        );

        return $this->successResponse(
            $this->applyRoleAwareStatusToOrder($order, $runnerUser),
            'Order accepted and assigned successfully.',
            200
        );
    }

    public function declineOrder($order_id): JsonResponse
    {
        $runnerUser = Auth::guard('api')->user();
        $runner = $runnerUser?->runner;

        if (! $runnerUser || ! $runner) {
            return $this->errorResponse('Runner profile is required to decline orders.', 403);
        }

        $order = Order::query()->findOrFail($order_id);

        if ((int) $order->runner_id !== (int) $runner->id) {
            return $this->errorResponse('This order is not assigned to you.', 403);
        }

        if ($order->status !== 'pending' || $order->runner_status !== 'pending') {
            return $this->errorResponse('Only newly assigned orders can be declined.', 422);
        }

        DB::transaction(function () use ($order) {
            $order->update([
                'status' => 'new',
                'runner_id' => null,
                'runner_status' => null,
            ]);
        });

        $order->refresh()->load(['user', 'runner.user', 'foodDelivery', 'ferryDrop']);

        $this->notifyAdmins(
            'Order Declined by Runner',
            "Order #{$order->id} has been declined and needs reassignment.",
            'order_declined',
            $order->id
        );

        $this->notifyUser(
            $order->user_id,
            'Order Reassignment In Progress',
            "Order #{$order->id} is pending reassignment after the assigned runner declined it.",
            'order_declined',
            $order->id
        );

        return $this->successResponse(
            $this->applyRoleAwareStatusToOrder($order, $runnerUser),
            'Order declined successfully.',
            200
        );
    }

    public function orderCompleted($order_id): JsonResponse
    {
        $runnerUser = Auth::guard('api')->user();
        $runner = $runnerUser?->runner;

        if (! $runnerUser || ! $runner) {
            return $this->errorResponse('Runner profile is required to complete orders.', 403);
        }

        $order = Order::query()->findOrFail($order_id);

        if ((int) $order->runner_id !== (int) $runner->id) {
            return $this->errorResponse('This order is not assigned to you.', 403);
        }

        if ($order->status !== 'pending' || $order->runner_status !== 'assigned') {
            return $this->errorResponse('Only ongoing orders can be completed.', 422);
        }

        DB::transaction(function () use ($order) {
            $order->update([
                'runner_status' => 'delivered',
                'status' => 'completed',
            ]);
        });

        $order->refresh()->load(['user', 'runner.user', 'foodDelivery', 'ferryDrop']);

        $this->notifyAdmins(
            'Order Completed',
            "Order #{$order->id} has been marked as completed by runner {$runnerUser->name}.",
            'order_completed',
            $order->id
        );

        $this->notifyUser(
            $order->user_id,
            'Confirm Order Completion',
            "Runner marked order #{$order->id} as completed. Please review and confirm completion.",
            'order_completion_confirmation',
            $order->id
        );

        return $this->successResponse(
            $this->applyRoleAwareStatusToOrder($order, $runnerUser),
            'Order marked as completed successfully.',
            200
        );
    }
}
