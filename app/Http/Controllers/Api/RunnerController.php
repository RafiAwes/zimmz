<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRunnerRequest;
use App\Http\Requests\Api\UpdateRunnerRequest;
use App\Models\Order;
use App\Models\Runner;
use App\Models\User;
use App\Traits\ApiResponseTraits;
use App\Traits\ImageTrait;
use App\Traits\NotificationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RunnerController extends Controller
{
    use ApiResponseTraits, ImageTrait, NotificationTrait;

    public function runnersList(Request $request): JsonResponse
    {
        return $this->getAll($request);
    }

    public function create(StoreRunnerRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $avatarPath = $this->uploadAvatar($request, 'avatar', 'images/user');

        try {
            return DB::transaction(function () use ($validated, $avatarPath) {
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'contact_number' => $validated['contact_number'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'avatar' => $avatarPath,
                    'role' => 'runner',
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]);

                Runner::create([
                    'user_id' => $user->id,
                    'category' => $validated['runner_category'],
                    'type' => $validated['runner_type'] ?? 'assigned',
                ]);

                return $this->successResponse($user->load('runner'), 'Runner created successfully.', 201);
            });
        } catch (\Exception $e) {
            if ($avatarPath) {
                $this->deleteImage($avatarPath);
            }

            return $this->errorResponse('Failed to create runner.', 500, $e->getMessage());
        }
    }

    public function updateRunner(UpdateRunnerRequest $request, int|string $id): JsonResponse
    {
        $validated = $request->validated();

        try {
            return DB::transaction(function () use ($validated, $id) {
                $runner = User::where('role', 'runner')->with('runner')->findOrFail($id);

                $userFields = array_filter([
                    'name' => $validated['name'] ?? null,
                    'email' => $validated['email'] ?? null,
                    'contact_number' => $validated['contact_number'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'password' => isset($validated['password']) ? Hash::make($validated['password']) : null,
                ], fn ($value) => ! is_null($value));

                if (! empty($userFields)) {
                    $runner->update($userFields);
                }

                $runnerFields = array_filter([
                    'category' => $validated['runner_category'] ?? null,
                    'type' => $validated['runner_type'] ?? null,
                ], fn ($value) => ! is_null($value));

                if (! empty($runnerFields)) {
                    $runner->runner->update($runnerFields);
                }

                return $this->successResponse($runner->fresh('runner'), 'Runner updated successfully.', 200);
            });
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update runner.', 500, $e->getMessage());
        }
    }

    public function getAll(Request $request): JsonResponse
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

    public function delete(int|string $id): JsonResponse
    {
        $runner = User::where('role', 'runner')->with('runner')->findOrFail($id);

        try {
            DB::transaction(function () use ($runner) {
                if ($runner->avatar) {
                    $this->deleteImage($runner->avatar);
                }

                $runner->delete();
            });

            return $this->successResponse(null, 'Runner deleted successfully.', 200);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete runner.', 500, $e->getMessage());
        }
    }

    public function details(int|string $id): JsonResponse
    {
        $runner = User::where('role', 'runner')->with('runner')->findOrFail($id);

        return $this->successResponse($runner, 'Runner details fetched successfully.', 200);
    }

    public function acceptOrder($order_id): JsonResponse
    {
        $runnerUser = Auth::guard('api')->user();

        if (! $runnerUser?->runner) {
            return $this->errorResponse('Runner profile is required to accept orders.', 403);
        }

        $order = Order::query()->findOrFail($order_id);

        if ((int) $order->runner_id !== (int) $runnerUser->id) {
            return $this->errorResponse('This order is not assigned to you.', 403);
        }

        if ($order->runner_status !== 'new') {
            return $this->errorResponse('Only newly assigned orders can be accepted.', 422);
        }

        DB::transaction(function () use ($order) {
            $order->update([
                'admin_status' => 'pending',
                'user_status' => 'ongoing',
                'runner_status' => 'ongoing',
            ]);
        });

        $order->refresh()->load(['user', 'foodDelivery', 'ferryDrop']);

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

        $order->setAttribute('status', $order->runner_status);

        return $this->successResponse($order, 'Order accepted successfully.', 200);
    }

    public function declineOrder($order_id): JsonResponse
    {
        $runnerUser = Auth::guard('api')->user();

        if (! $runnerUser?->runner) {
            return $this->errorResponse('Runner profile is required to decline orders.', 403);
        }

        $order = Order::query()->findOrFail($order_id);

        if ((int) $order->runner_id !== (int) $runnerUser->id) {
            return $this->errorResponse('This order is not assigned to you.', 403);
        }

        if ($order->runner_status !== 'new') {
            return $this->errorResponse('Only newly assigned orders can be declined.', 422);
        }

        DB::transaction(function () use ($order) {
            $order->update([
                'admin_status' => 'new',
                'user_status' => 'pending',
                'runner_status' => null,
                'runner_id' => null,
            ]);
        });

        $order->refresh()->load(['user', 'foodDelivery', 'ferryDrop']);

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

        $order->setAttribute('status', $order->runner_status);

        return $this->successResponse($order, 'Order declined successfully.', 200);
    }

    public function orderCompleted($order_id): JsonResponse
    {
        $runnerUser = Auth::guard('api')->user();

        if (! $runnerUser?->runner) {
            return $this->errorResponse('Runner profile is required to complete orders.', 403);
        }

        $order = Order::query()->findOrFail($order_id);

        if ((int) $order->runner_id !== (int) $runnerUser->id) {
            return $this->errorResponse('This order is not assigned to you.', 403);
        }

        if ($order->runner_status !== 'ongoing') {
            return $this->errorResponse('Only ongoing orders can be completed.', 422);
        }

        DB::transaction(function () use ($order) {
            $order->update([
                'runner_status' => 'completed',
                'user_status' => 'pending',
                'delivery_requested' => true,
            ]);
        });

        $order->refresh()->load(['user', 'foodDelivery', 'ferryDrop']);

        $this->notifyAdmins(
            'Order Completed by Runner',
            "Runner {$runnerUser->name} has completed order #{$order->id}. Please request delivery confirmation from the user.",
            'order_completed',
            $order->id
        );

        $order->setAttribute('status', $order->runner_status);

        return $this->successResponse($order, 'Order marked as completed. Admin will confirm delivery with user.', 200);
    }
}
