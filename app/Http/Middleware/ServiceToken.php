<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ServiceToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
         abort_unless(
            hash_equals((string) config('services.internal.token'), (string) $request->bearerToken()),
            401
        );
        return $next($request);
    }
}
