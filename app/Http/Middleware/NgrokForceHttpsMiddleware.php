<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class NgrokForceHttpsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        URL::forceHttps($this->isNgrokRequest($request->getHost()));

        return $next($request);
    }

    private function isNgrokRequest(string $host): bool
    {
        if (str_ends_with($host, '.ngrok-free.app')) {
            return true;
        }

        return false;
    }
}
