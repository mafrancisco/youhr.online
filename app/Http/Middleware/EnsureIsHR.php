<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureIsHR
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || !$request->user()->isHR()) {
            abort(403, 'Access denied.');
        }
        return $next($request);
    }
}
