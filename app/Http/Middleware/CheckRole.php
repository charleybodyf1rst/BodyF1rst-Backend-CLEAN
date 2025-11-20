<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Models\Coach;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Token;

class CheckRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip authentication check for CORS preflight OPTIONS requests
        if ($request->getMethod() === 'OPTIONS') {
            return $next($request);
        }

        // Get the Bearer token from the request
        $bearerToken = $request->bearerToken();

        if ($bearerToken) {
            // Passport JWT tokens - decode to get token ID
            try {
                // Decode JWT to get jti (token ID)
                $tokenParts = explode('.', $bearerToken);
                if (count($tokenParts) === 3) {
                    $payload = json_decode(base64_decode($tokenParts[1]), true);
                    $jti = $payload['jti'] ?? null;

                    if ($jti) {
                        // Query oauth_access_tokens using the jti
                        $userId = DB::table('oauth_access_tokens')
                            ->where('id', $jti)
                            ->where('revoked', 0)
                            ->where('expires_at', '>', now())
                            ->value('user_id');

                        if ($userId) {
                            // Try to find admin
                            $admin = Admin::find($userId);
                            if ($admin && $admin->is_active == 1) {
                                // Set the authenticated admin on the request
                                Auth::guard('admin')->setUser($admin);
                                $request->merge(['role' => 'Admin', 'authenticated_user' => $admin]);
                                return $next($request);
                            }

                            // Try to find coach
                            $coach = Coach::find($userId);
                            if ($coach && $coach->is_active == 1) {
                                // Set the authenticated coach on the request
                                Auth::guard('coach')->setUser($coach);
                                $request->merge(['role' => 'Coach', 'authenticated_user' => $coach]);
                                return $next($request);
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                // Token decoding/validation failed - continue to fallback auth
            }
        }

        // Try fallback auth guards for session-based auth
        if ($admin = Auth::guard('admin')->user()) {
            $request->merge(['role' => 'Admin']);
            return $next($request);
        }

        if ($coach = Auth::guard('coach')->user()) {
            $request->merge(['role' => 'Coach']);
            return $next($request);
        }

        // Unauthorized Response
        return response()->json(["message" => "Only Admins and Coaches are authorized to perform these actions"], 401);
    }
}
