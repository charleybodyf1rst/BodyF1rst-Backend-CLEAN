<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;

class ThrottleFileUploads
{
    protected $limiter;

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $key = 'upload:' . ($request->user() ? $request->user()->id : $request->ip());

        // 50 uploads per 5 minutes (increased limit for active users)
        if ($this->limiter->tooManyAttempts($key, 50)) {
            return response()->json([
                'success' => false,
                'message' => 'Upload limit exceeded. Please wait 5 minutes before uploading again.',
                'retry_after' => $this->limiter->availableIn($key)
            ], 429);
        }

        $this->limiter->hit($key, 300); // 5 minutes (300 seconds) TTL

        $response = $next($request);

        // Add rate limit headers
        $response->headers->set('X-RateLimit-Limit', 50);
        $response->headers->set('X-RateLimit-Remaining', max(0, 50 - $this->limiter->attempts($key)));

        return $response;
    }
}
