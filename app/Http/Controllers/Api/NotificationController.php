<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Traits\ApiResponseTraits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    use ApiResponseTraits;

    public function notifications(Request $request): JsonResponse
    {
        $user = Auth::guard('api')->user();
        $per_page = $request->input('per_page', 10);
        $is_read = $request->input('is_read');

        $notifications = Notification::query()
            ->where('user_id', $user->id)
            ->when($is_read !== null, function ($query) use ($is_read) {
                $query->where('is_read', $is_read);
            })
            ->with('order')
            ->latest()
            ->paginate($per_page);

        return $this->successResponse($notifications, 'Notifications fetched successfully.', 200);
    }

    public function markAsRead(Request $request, int|string $id): JsonResponse
    {
        $user = Auth::guard('api')->user();

        $notification = Notification::query()
            ->where('user_id', $user->id)
            ->findOrFail($id);

        $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return $this->successResponse($notification, 'Notification marked as read.', 200);
    }

    public function readAll(): JsonResponse
    {
        $user = Auth::guard('api')->user();

        Notification::query()
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return $this->successResponse(null, 'All notifications marked as read.', 200);
    }

    /**
     * Backward-compatible alias for older clients.
     */
    public function getAll(Request $request): JsonResponse
    {
        return $this->notifications($request);
    }

    /**
     * Backward-compatible alias for older clients.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        return $this->readAll();
    }

    public function getUnreadCount(Request $request): JsonResponse
    {
        $user = Auth::guard('api')->user();

        $count = Notification::query()
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return $this->successResponse(['count' => $count], 'Unread notification count fetched successfully.', 200);
    }

    public function delete(Request $request, $id): JsonResponse
    {
        $user = Auth::guard('api')->user();

        $notification = Notification::query()
            ->where('user_id', $user->id)
            ->findOrFail($id);

        $notification->delete();

        return $this->successResponse(null, 'Notification deleted successfully.', 200);
    }
}
