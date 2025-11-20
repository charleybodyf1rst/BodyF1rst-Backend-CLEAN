<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CSPNonceMiddleware
{
    /**
     * Handle an incoming request and add CSP nonce.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Generate cryptographically strong nonce
        $nonce = base64_encode(random_bytes(16));
        
        // Store nonce in request for use in views
        $request->attributes->set('csp_nonce', $nonce);
        
        $response = $next($request);
        
        // Only add CSP header for HTML responses
        if ($response instanceof Response && 
            str_contains($response->headers->get('Content-Type', ''), 'text/html')) {
            
            $csp = $this->buildCSPHeader($nonce);
            $response->headers->set('Content-Security-Policy', $csp);
        }
        
        return $response;
    }
    
    /**
     * Build the Content Security Policy header with nonce.
     *
     * @param string $nonce
     * @return string
     */
    private function buildCSPHeader(string $nonce): string
    {
        $policies = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'nonce-{$nonce}'", // Removed unsafe-inline for better security
            "img-src 'self' data: https:",
            "font-src 'self' data:",
            "connect-src 'self' https:",
            "frame-ancestors 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'"
        ];
        
        return implode('; ', $policies) . ';';
    }
}
