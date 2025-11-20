<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckAdminTokenExpiration
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
        $user = Auth::guard('admin')->user();
        $coach = Auth::guard('coach')->user();

        if ($user) {
            $token = $user->token();
            if ($token) {
                $tokenCreationTime = $token->created_at;

                $tokenCreationTime = Carbon::parse($tokenCreationTime);

                $expiresAt = $tokenCreationTime->addDay(1);

                if (Carbon::now()->isAfter($expiresAt)) {
                    $token->revoke();
                    $response = [
                        "status" => 422,
                        "message" => "Token Expired!",
                    ];
                    return response($response ,$response['status']);
                }
            }
        }
        else if ($coach) {
            $token = $coach->token();
            if ($token) {
                $tokenCreationTime = $token->created_at;

                $tokenCreationTime = Carbon::parse($tokenCreationTime);

                $expiresAt = $tokenCreationTime->addDay(1);

                if (Carbon::now()->isAfter($expiresAt)) {
                    $token->revoke();
                    $response = [
                        "status" => 422,
                        "message" => "Token Expired!",
                    ];
                    return response($response ,$response['status']);
                }
            }
        }


        return $next($request);
    }
}
