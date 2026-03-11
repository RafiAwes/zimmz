<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRunnerRequest;
use App\Models\Order;
use App\Models\Runner;
use App\Models\User;
use App\Traits\ApiResponseTraits;
use App\Traits\NotificationTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RunnerController extends Controller
{
    use ApiResponseTraits;
    use NotificationTrait;

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
            ->paginate($per_page);

        return $this->successResponse($runners, 'Runners fetched successfully.', 200);
    }

    public function details($id)
    {
        $runner = User::where('role', 'runner')->with('runner')->findOrFail($id);

        return $this->successResponse($runner, 'Runner details fetched successfully.', 200);
    }

    public function acceptOrder($order_id)
    {
        $order = DB::transaction(function () use ($order_id) {
            $order = Order::query()->findOrFail($order_id);

            $order->update([
                'runner_status' => 'assigned',
            ]);

            return $order;
        });

        // Notify admin and user who placed the order
        $this->notifyAdmins(
            'Order Accepted by Runner',
            "Order #{$order->id} has been accepted by runner {$order->runner->user->name}.",
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
            $order->load(['user', 'runner.user', 'foodDelivery', 'ferryDrop']),
            'Order accepted and assigned successfully.',
            200
        );
    }

    public function declineOrder($order_id)
    {
        $order = DB::transaction(function () use ($order_id) {
            $order = Order::query()->findOrFail($order_id);

            $order->update([
                'runner_id' => null,
                'runner_status' => null,
            ]);

            return $order;
        });

        // Notify admin about declined order
        $this->notifyAdmins(
            'Order Declined by Runner',
            "Order #{$order->id} has been declined and needs reassignment.",
            'order_declined',
            $order->id
        );

        return $this->successResponse(
            $order->load(['user', 'runner.user', 'foodDelivery', 'ferryDrop']),
            'Order declined successfully.',
            200
        );
    }

    public function orderCompleted($order_id)
    {
        $order = DB::transaction(function () use ($order_id) {
            $order = Order::query()->findOrFail($order_id);

            $order->update([
                'runner_status' => 'delivered',
                'status' => 'completed',
            ]);

            return $order;
        });

        // Notify admin and user about completed order
        $this->notifyAdmins(
            'Order Completed',
            "Order #{$order->id} has been marked as completed by runner {$order->runner->user->name}.",
            'order_completed',
            $order->id
        );

        $this->notifyUser(
            $order->user_id,
            'Your Order is Completed',
            "Your order #{$order->id} has been successfully delivered!",
            'order_completed',
            $order->id
        );

        return $this->successResponse(
            $order->load(['user', 'runner.user', 'foodDelivery', 'ferryDrop']),
            'Order marked as completed successfully.',
            200
        );
    }
}
