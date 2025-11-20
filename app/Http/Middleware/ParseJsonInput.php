<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ParseJsonInput
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('POST') || $request->isMethod('PUT') || $request->isMethod('PATCH')) {
            $contentType = $request->header('Content-Type');
            if (str_contains($contentType, 'application/json')) {
                $input = file_get_contents('php://input');
                if (!empty($input)) {
                    $data = json_decode($input, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                        $request->merge($data);
                    }
                }
            }
        }
        return $next($request);
    }
}
