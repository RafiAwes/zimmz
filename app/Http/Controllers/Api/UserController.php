<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponseTraits;

class UserController extends Controller
{
    use ApiResponseTraits;

    public function details($id)
    {
        $user = User::findOrFail($id);

        if ($user->role === 'runner') {
            $user->load('runner');
        }

        return $this->successResponse($user, 'User details fetched successfully.', 200);
    }
}
