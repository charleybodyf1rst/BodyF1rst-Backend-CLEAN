<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;

class ThrottlePassioRequests
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
        $key = 'passio:' . ($request->user() ? $request->user()->id : $request->ip());

        // 200 requests per minute for Passio endpoints (generous limit for AI chat)
        if ($this->limiter->tooManyAttempts($key, 200)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many Passio API requests. Please wait before trying again.',
                'retry_after' => $this->limiter->availableIn($key)
            ], 429);
        }

        $this->limiter->hit($key, 60); // 60 seconds TTL

        $response = $next($request);

        // Add rate limit headers
        $response->headers->set('X-RateLimit-Limit', 200);
        $response->headers->set('X-RateLimit-Remaining', max(0, 200 - $this->limiter->attempts($key)));

        return $response;
    }
}
