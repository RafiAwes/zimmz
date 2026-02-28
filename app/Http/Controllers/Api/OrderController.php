<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Order\StoreOrderRequest;
use App\Http\Requests\Api\Order\UpdateOrderRequest;
use App\Models\FerryDrop;
use App\Models\FoodDelivery;
use App\Models\Order;
use App\Traits\ApiResponseTraits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    use ApiResponseTraits;

    public function getAll(Request $request): JsonResponse
    {
        $status = $request->input('status');
        $per_page = $request->input('per_page', 10);

        if (is_null($status)) {
            $orders = Order::query()
                ->with(['user', 'foodDelivery', 'ferryDrop'])
                ->latest()
                ->paginate($per_page);
        } else {
            $orders = Order::query()
                ->with(['user', 'foodDelivery', 'ferryDrop'])
                ->where('status', $status)
                ->latest()
                ->paginate($per_page);
        }

        return $this->successResponse($orders, 'Orders fetched successfully.', 200);
    }

    public function create(StoreOrderRequest $request): JsonResponse
    {
        $order = DB::transaction(function () use ($request) {
            $order = Order::create([
                'user_id' => Auth::id() ?? 1, // Fallback for testing if no auth
                'name' => $request->name,
                'status' => 'new',
                'details' => $request->details,
                'time' => $request->time,
                'total_cost' => $request->total_cost,
                'drop_location' => $request->drop_location,
                'type' => $request->type,
                'files' => $this->handleFileUploads($request),
            ]);

            if ($request->type === 'food_delivery') {
                FoodDelivery::create([
                    'order_id' => $order->id,
                    'restaurant_id' => $request->restaurant_id,
                    'food_cost' => $request->food_cost,
                    'special_instructions' => $request->special_instructions,
                    'ready_now' => $request->ready_now ?? false,
                    'minutes_until_ready' => $request->minutes_until_ready,
                    'delivery_fee' => $request->delivery_fee,
                    'service_fee' => $request->service_fee,
                ]);
            } elseif ($request->type === 'ferry_drops') {
                FerryDrop::create([
                    'order_id' => $order->id,
                    'pickup_location' => $request->pickup_location,
                    'ferry_id' => $request->ferry_id,
                    'island_id' => $request->island_id,
                    'drop_fee' => $request->drop_fee,
                    'package_fee' => $request->package_fee,
                ]);
            }

            return $order->load(['foodDelivery', 'ferryDrop']);
        });

        return $this->successResponse($order, 'Order created successfully.', 201);
    }

    public function details(Request $request, $id): JsonResponse
    {
        $order = Order::with(['user', 'foodDelivery', 'ferryDrop'])->findOrFail($id);

        return $this->successResponse($order, 'Order details fetched successfully.', 200);
    }

    public function update(UpdateOrderRequest $request, $id): JsonResponse
    {
        $order = Order::findOrFail($id);
        $order = DB::transaction(function () use ($request, $order) {
            $order->update($request->only([
                'name',
                'status',
                'details',
                'time',
                'total_cost',
                'drop_location',
            ]));

            if ($request->hasFile('files')) {
                $existingFiles = $order->files ?? [];
                $newFiles = $this->handleFileUploads($request);
                $order->update(['files' => array_merge($existingFiles, $newFiles)]);
            }

            if ($order->type === 'food_delivery') {
                $order->foodDelivery()->update($request->only([
                    'food_cost',
                    'special_instructions',
                    'ready_now',
                    'minutes_until_ready',
                    'delivery_fee',
                    'service_fee',
                ]));
            } elseif ($order->type === 'ferry_drops') {
                $order->ferryDrop()->update($request->only([
                    'pickup_location',
                    'drop_fee',
                    'package_fee',
                ]));
            }

            return $order->load(['foodDelivery', 'ferryDrop']);
        });

        return $this->successResponse($order, 'Order updated successfully.', 200);
    }

    public function delete(Request $request, $id): JsonResponse
    {
        $order = Order::findOrFail($id);
        $order->delete();

        return $this->successResponse(null, 'Order deleted successfully.', 200);
    }

    protected function handleFileUploads(Request $request): array
    {
        $filePaths = [];

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('orders', 'public');
                $filePaths[] = $path;
            }
        }

        return $filePaths;
    }
}
