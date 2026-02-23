<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Traits\ApiResponseTraits;

class IsRunner
{
    use ApiResponseTraits;

    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('api')->user();

        if ($user && $user->role === 'runner') {
            return $next($request);
        }

        return $this->errorResponse('Access denied. Runner privileges required.', 403);
    }
}