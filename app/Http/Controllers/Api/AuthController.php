<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        try {
            // Rate limiting for login attempts
            $rawEmail = (string) $request->input('email', '');
            $normalizedEmail = strtolower(trim($rawEmail));
            if ($normalizedEmail !== '' && !filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
                $normalizedEmail = '';
            }
            $emailKey = $normalizedEmail !== '' ? substr(hash('sha256', $normalizedEmail), 0, 32) : 'unknown';
            $rateLimitKey = 'login:' . $request->ip() . ':' . $emailKey;
            
            if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
                $seconds = RateLimiter::availableIn($rateLimitKey);
                return response()->json([
                    'ok' => false,
                    'success' => false,
                    'message' => 'Too many login attempts. Please try again in ' . $seconds . ' seconds.',
                    'retry_after' => $seconds
                ], 429);
            }

            // Validate input credentials
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => ['required', 'string', 'min:12', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).+$/'],
            ], [
                'password.min' => 'Password must be at least 12 characters long.',
                'password.regex' => 'Password must include at least one uppercase letter, one lowercase letter, one number, and one symbol.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'ok' => false,
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Find user by email
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                // Hit rate limiter on failed attempt
                RateLimiter::hit($rateLimitKey, 300); // 5 minute decay
                return response()->json([
                    'ok' => false,
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            // Verify password
            if (!Hash::check($request->password, $user->password)) {
                // Hit rate limiter on failed attempt
                RateLimiter::hit($rateLimitKey, 300); // 5 minute decay
                return response()->json([
                    'ok' => false,
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            // Check if user is active (if you have this field)
            if (isset($user->is_active) && !$user->is_active) {
                return response()->json([
                    'ok' => false,
                    'success' => false,
                    'message' => 'Account is disabled'
                ], 401);
            }

            // Clear rate limiter on successful login
            RateLimiter::clear($rateLimitKey);

            // Revoke existing tokens for security
            $user->tokens()->delete();

            // Create new Sanctum token
            $token = $user->createToken('mobile-app')->plainTextToken;

            return response()->json([
                'ok' => true,
                'success' => true,
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                ]
            ], 200);

        } catch (\Exception $e) {
            // Log the full exception for debugging (without PII)
            $rawEmail = (string) $request->input('email', '');
            $normalizedEmail = strtolower(trim($rawEmail));
            if ($normalizedEmail !== '' && !filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
                $normalizedEmail = '';
            }
            $emailHash = $normalizedEmail !== '' ? hash('sha256', $normalizedEmail) : 'unknown';
            Log::error('Login error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'email_hash' => $emailHash, // Non-reversible identifier
                'ip' => $request->ip(),
            ]);

            // Return generic error message to prevent information leakage
            return response()->json([
                'ok' => false,
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again.'
            ], 500);
        }
    }

    public function logout(Request $r)
    {
        $r->user()?->currentAccessToken()?->delete();
        return response()->json(['ok' => true], 200);
    }
}
