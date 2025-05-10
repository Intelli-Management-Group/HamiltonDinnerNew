<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckPermission
{
    public function handle(Request $request, Closure $next, $permission)
    {
        if (Auth::guard('api')->guest()) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }
        
        if (! Auth::guard('api')->user()->hasPermissionTo($permission)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return $next($request);
    }
}