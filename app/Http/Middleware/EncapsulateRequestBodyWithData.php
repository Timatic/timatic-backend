<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EncapsulateRequestBodyWithData
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if (! $request->has('data') && in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            $request->replace(['data' => $request->all()]);
        }

        return $next($request);
    }
}
