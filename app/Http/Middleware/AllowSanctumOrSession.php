<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AllowSanctumOrSession
{
    /**
     * Handle an incoming request.
     * Allows authentication via Sanctum token OR session (for admin panel iframe embedding)
     * This is needed when the webapp is embedded in an iframe and uses session cookies
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // EnsureFrontendRequestsAreStateful middleware (in api group) should handle session cookies
        // We just need to check both authentication methods
        
        // Try to get user from Sanctum token first (for regular users)
        $user = Auth::guard('sanctum')->user();
        
        // If no Sanctum user, try web guard (session-based auth for admin panel iframe)
        // EnsureFrontendRequestsAreStateful should have made the session available
        if (!$user) {
            $user = Auth::guard('web')->user();
        }
        
        // If we found a user via web guard, set it for the default guard so Auth::user() works
        if ($user && !Auth::check()) {
            Auth::setUser($user);
        }
        
        // If still no user, reject with JSON (not redirect)
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }
        
        return $next($request);
    }
}





















