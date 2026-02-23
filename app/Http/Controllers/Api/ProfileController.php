<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponseTraits;
use App\Traits\ImageTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    use ApiResponseTraits, ImageTrait;

    public function updateProfile(Request $request)
    {
        $user = Auth::guard('api')->user();

        // Validate all possible fields
        $rules = [
            'name' => 'nullable|string|max:255',
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'contact_number' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'password' => 'nullable|string|min:6',
            'runner_category' => 'nullable|string|max:255',
            'gender' => 'nullable|in:male,female,other',
        ];

        $request->validate($rules);

        // Determine allowed fields based on role and runner type
        $allowedUserFields = [];
        $allowedRunnerFields = [];

        if ($user->role === 'runner' && $user->runner) {
            if ($user->runner->type === 'assigned') {
                $allowedUserFields = ['name', 'email', 'contact_number', 'address', 'password'];
                $allowedRunnerFields = ['category']; // maps from runner_category
            } elseif (in_array($user->runner->type, ['registered', 'registeres'])) {
                $allowedUserFields = ['name', 'email', 'contact_number', 'address'];
            }
        } elseif ($user->role === 'user') {
            $allowedUserFields = ['name', 'email', 'address'];
        }

        try {
            // Update User fields
            foreach ($allowedUserFields as $field) {
                if ($field === 'contact_number') {
                    if ($request->filled('contact_number')) {
                        $user->contact_number = $request->contact_number;
                    } elseif ($request->filled('phone')) {
                        $user->contact_number = $request->phone;
                    }
                } elseif ($field === 'password') {
                    if ($request->filled('password')) {
                        $user->password = \Illuminate\Support\Facades\Hash::make($request->password);
                    }
                } elseif ($request->filled($field)) {
                    $user->$field = $request->$field;
                }
            }

            $user->save();

            // Update Runner fields if applicable
            if (! empty($allowedRunnerFields) && $user->runner) {
                if (in_array('category', $allowedRunnerFields) && $request->filled('runner_category')) {
                    $user->runner->category = $request->runner_category;
                    $user->runner->save();
                }
            }

            return $this->successResponse($user->load('runner'), 'Profile info updated successfully.', 200);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to update profile info',
                500,
                $e->getMessage().' at Line: '.$e->getLine()
            );
        }
    }

    public function updateAvatar(Request $request)
    {
        $user = Auth::guard('api')->user();

        // 1. Strict Image Validation
        $rules = [
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // Max 2MB
        ];

        $request->validate($rules);

        try {
            // 2. Upload new avatar using your custom ImageTrait
            $imagePath = $this->uploadAvatar($request, 'avatar', 'images/user');

            // 3. Keep the server clean by deleting the old avatar
            $oldAvatar = $user->getRawOriginal('avatar');
            if ($oldAvatar) {
                $this->deleteImage($oldAvatar);
            }

            // 4. Update user record
            $user->update(['avatar' => $imagePath]);

            return $this->successResponse($user, 'Avatar updated successfully.', 200);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to update avatar',
                500,
                $e->getMessage().' at Line: '.$e->getLine()
            );
        }
    }
}
