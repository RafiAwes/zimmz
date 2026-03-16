<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponseTraits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ApiResponseTraits;

    public function usersList(Request $request)
    {
        $per_page = $request->per_page ?? 5;
        $search = $request->search;

        $users = User::query()
            ->where('role', 'user')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('contact_number', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate($per_page);

        return $this->successResponse($users, 'Users fetched successfully.', 200);
    }

    public function lostUsers(Request $request)
    {
        $per_page = $request->per_page ?? 5;
        $search = $request->search;
        $ban_type = $request->ban_type;

        $users = User::query()
            ->where('role', 'user')
            ->where(function ($query) {
                $query->where('is_active', false)
                    ->orWhere(function ($banQuery) {
                        $banQuery->whereNotNull('ban_type')
                            ->where(function ($banExpiryQuery) {
                                $banExpiryQuery->whereNull('ban_expires_at')
                                    ->orWhere('ban_expires_at', '>', now());
                            });
                    });
            })
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('contact_number', 'like', "%{$search}%");
                });
            })
            ->when($ban_type, function ($query, $ban_type) {
                $query->where('ban_type', $ban_type);
            })
            ->orderByDesc('id')
            ->paginate($per_page);

        return $this->successResponse($users, 'Lost users fetched successfully.', 200);
    }

    public function details($id)
    {
        $user = User::findOrFail($id);

        if ($user->role === 'runner') {
            $user->load('runner');
        }

        return $this->successResponse($user, 'User details fetched successfully.', 200);
    }

    public function delete(int|string $id): JsonResponse
    {
        $user = User::query()
            ->where('role', 'user')
            ->findOrFail($id);

        $user->delete();

        return $this->successResponse(null, 'User deleted successfully.', 200);
    }
}
