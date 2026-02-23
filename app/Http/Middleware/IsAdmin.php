<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Traits\ApiResponseTraits;

class IsAdmin
{
    use ApiResponseTraits;

    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('api')->user();

        if ($user && $user->role === 'admin') {
            return $next($request);
        }

        return $this->errorResponse('Access denied. Admin privileges required.', 403);
    }
}