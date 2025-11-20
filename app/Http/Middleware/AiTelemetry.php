<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiTelemetry {
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $start = hrtime(true);
        $resp  = $next($request);
        $ms = (hrtime(true) - $start) / 1e6;

        $safe = fn($s) => Str::limit(preg_replace('/\b([\w.%+-]+@[\w.-]+\.[A-Za-z]{2,})\b/', '[email]', (string)$s), 500);
        
        Log::channel('daily')->info('ai_trace', [
            'route' => $request->path(), 
            'latency_ms' => $ms, 
            'user_id' => optional($request->user())->id,
            // add tokens when you capture them from the SDK response
        ]);
        
        return $resp;
    }
}
