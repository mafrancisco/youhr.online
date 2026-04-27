<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureIsEmployee
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || !$request->user()->isEmployee()) {
            abort(403, 'Access denied.');
        }
        return $next($request);
    }
}
