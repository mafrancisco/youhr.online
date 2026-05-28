<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckInstalled
{
    public function handle(Request $request, Closure $next)
    {
        // Skip if accessing the installer itself
        if (str_starts_with($request->path(), 'install')) {
            return $next($request);
        }

        // Check if system is installed
        if (!file_exists(storage_path('installed.lock'))) {
            return redirect('/install/');
        }

        return $next($request);
    }
}
