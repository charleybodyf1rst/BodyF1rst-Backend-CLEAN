<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Stripe\Stripe;
use Stripe\Account;
use Stripe\AccountLink;
use Stripe\Transfer;
use Stripe\Payout;
use Carbon\Carbon;

class CoachStripeConnectController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(env('STRIPE_SECRET_KEY'));
    }

    /**
     * Get coach's Stripe Connect onboarding link
     * POST /api/coach/connect-stripe
     */
    public function connectStripe(Request $request)
    {
        $userId = Auth::id();
        $user = User::findOrFail($userId);

        // Verify user is a coach
        if ($user->role !== 'coach' && !$user->hasRole('coach')) {
            return response()->json([
                'success' => false,
                'message' => 'Only coaches can connect Stripe accounts'
            ], 403);
        }

        try {
            $stripeConnectAccountId = $user->stripe_connect_account_id;

            // If no Stripe Connect account exists, create one
            if (!$stripeConnectAccountId) {
                $account = Account::create([
                    'type' => 'express',
                    'country' => $request->get('country', 'US'),
                    'email' => $user->email,
                    'capabilities' => [
                        'card_payments' => ['requested' => true],
                        'transfers' => ['requested' => true],
                    ],
                    'business_type' => $request->get('business_type', 'individual'),
                    'metadata' => [
                        'user_id' => $userId,
                        'coach_name' => $user->first_name . ' ' . $user->last_name
                    ]
                ]);

                // Save Stripe Connect account ID
                $user->update(['stripe_connect_account_id' => $account->id]);
                $stripeConnectAccountId = $account->id;
            }

            // Create account link for onboarding
            $accountLink = AccountLink::create([
                'account' => $stripeConnectAccountId,
                'refresh_url' => $request->get('refresh_url', env('APP_URL') . '/coach/stripe-connect'),
                'return_url' => $request->get('return_url', env('APP_URL') . '/coach/earnings'),
                'type' => 'account_onboarding',
            ]);

            return response()->json([
                'success' => true,
                'onboarding_url' => $accountLink->url,
                'stripe_account_id' => $stripeConnectAccountId
            ]);

        } catch (\Exception $e) {
            Log::error('Stripe Connect onboarding failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create Stripe Connect account: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get coach's Stripe Connect status
     * GET /api/coach/stripe-status
     */
    public function getStripeStatus()
    {
        $userId = Auth::id();
        $user = User::findOrFail($userId);

        if (!$user->stripe_connect_account_id) {
            return response()->json([
                'success' => true,
                'connected' => false,
                'status' => 'not_connected',
                'message' => 'No Stripe Connect account linked'
            ]);
        }

        try {
            $account = Account::retrieve($user->stripe_connect_account_id);

            $status = [
                'connected' => true,
                'charges_enabled' => $account->charges_enabled,
                'payouts_enabled' => $account->payouts_enabled,
                'details_submitted' => $account->details_submitted,
                'requirements' => [
                    'currently_due' => $account->requirements->currently_due ?? [],
                    'eventually_due' => $account->requirements->eventually_due ?? [],
                    'past_due' => $account->requirements->past_due ?? [],
                ]
            ];

            // Determine overall status
            if ($account->charges_enabled && $account->payouts_enabled) {
                $status['status'] = 'active';
                $status['message'] = 'Your Stripe account is fully active and ready to receive payments';
            } elseif ($account->details_submitted) {
                $status['status'] = 'pending';
                $status['message'] = 'Your account is under review. You will be able to receive payments soon.';
            } else {
                $status['status'] = 'incomplete';
                $status['message'] = 'Please complete your Stripe account setup to receive payments';
            }

            return response()->json([
                'success' => true,
                ...$status
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve Stripe Connect status', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve Stripe status'
            ], 500);
        }
    }

    /**
     * Get coach's earnings dashboard
     * GET /api/coach/earnings
     */
    public function getEarnings(Request $request)
    {
        $userId = Auth::id();
        $user = User::findOrFail($userId);

        if (!$user->stripe_connect_account_id) {
            return response()->json([
                'success' => true,
                'total_earnings' => 0,
                'available_balance' => 0,
                'pending_balance' => 0,
                'recent_payments' => [],
                'message' => 'Connect your Stripe account to start receiving payments'
            ]);
        }

        try {
            $account = Account::retrieve($user->stripe_connect_account_id);

            // Get balance
            $balance = \Stripe\Balance::retrieve([
                'stripe_account' => $user->stripe_connect_account_id
            ]);

            $availableBalance = 0;
            $pendingBalance = 0;

            foreach ($balance->available as $item) {
                $availableBalance += $item->amount / 100; // Convert from cents
            }

            foreach ($balance->pending as $item) {
                $pendingBalance += $item->amount / 100;
            }

            // Get recent transfers/payouts
            $transfers = Transfer::all([
                'destination' => $user->stripe_connect_account_id,
                'limit' => 10
            ]);

            $recentPayments = [];
            foreach ($transfers->data as $transfer) {
                $recentPayments[] = [
                    'id' => $transfer->id,
                    'amount' => $transfer->amount / 100,
                    'currency' => strtoupper($transfer->currency),
                    'date' => Carbon::createFromTimestamp($transfer->created)->format('Y-m-d H:i:s'),
                    'status' => 'completed',
                    'description' => $transfer->description ?? 'Client session payment'
                ];
            }

            // Calculate total earnings (from database or Stripe metadata)
            $totalEarnings = DB::table('coach_session_payments')
                ->where('coach_id', $userId)
                ->where('status', 'paid')
                ->sum('amount') ?? 0;

            return response()->json([
                'success' => true,
                'total_earnings' => $totalEarnings,
                'available_balance' => $availableBalance,
                'pending_balance' => $pendingBalance,
                'currency' => 'USD',
                'recent_payments' => $recentPayments,
                'stripe_account_id' => $user->stripe_connect_account_id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve coach earnings', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve earnings data'
            ], 500);
        }
    }

    /**
     * Request instant payout
     * POST /api/coach/payout
     */
    public function requestPayout(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1'
        ]);

        $userId = Auth::id();
        $user = User::findOrFail($userId);

        if (!$user->stripe_connect_account_id) {
            return response()->json([
                'success' => false,
                'message' => 'Please connect your Stripe account first'
            ], 400);
        }

        try {
            // Check available balance
            $balance = \Stripe\Balance::retrieve([
                'stripe_account' => $user->stripe_connect_account_id
            ]);

            $availableAmount = 0;
            foreach ($balance->available as $item) {
                if ($item->currency === 'usd') {
                    $availableAmount = $item->amount / 100;
                    break;
                }
            }

            if ($validated['amount'] > $availableAmount) {
                return response()->json([
                    'success' => false,
                    'message' => "Insufficient balance. Available: $" . number_format($availableAmount, 2)
                ], 400);
            }

            // Create instant payout
            $payout = Payout::create([
                'amount' => $validated['amount'] * 100, // Convert to cents
                'currency' => 'usd',
                'method' => 'instant', // Instant payout (costs 1.5% fee)
                'description' => 'BodyF1rst coach payout',
                'metadata' => [
                    'user_id' => $userId,
                    'requested_at' => now()->toIso8601String()
                ]
            ], [
                'stripe_account' => $user->stripe_connect_account_id
            ]);

            // Log payout request
            DB::table('coach_payout_requests')->insert([
                'coach_id' => $userId,
                'amount' => $validated['amount'],
                'stripe_payout_id' => $payout->id,
                'status' => $payout->status,
                'requested_at' => now(),
                'created_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'payout' => [
                    'id' => $payout->id,
                    'amount' => $validated['amount'],
                    'status' => $payout->status,
                    'arrival_date' => Carbon::createFromTimestamp($payout->arrival_date)->format('Y-m-d'),
                    'method' => $payout->method
                ],
                'message' => 'Payout requested successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Payout request failed', [
                'user_id' => $userId,
                'amount' => $validated['amount'],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process payout: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payout history
     * GET /api/coach/payouts
     */
    public function getPayoutHistory(Request $request)
    {
        $userId = Auth::id();
        $user = User::findOrFail($userId);

        if (!$user->stripe_connect_account_id) {
            return response()->json([
                'success' => true,
                'payouts' => []
            ]);
        }

        try {
            $limit = $request->get('limit', 10);

            $payouts = Payout::all([
                'limit' => $limit
            ], [
                'stripe_account' => $user->stripe_connect_account_id
            ]);

            $payoutHistory = [];
            foreach ($payouts->data as $payout) {
                $payoutHistory[] = [
                    'id' => $payout->id,
                    'amount' => $payout->amount / 100,
                    'currency' => strtoupper($payout->currency),
                    'status' => $payout->status,
                    'method' => $payout->method,
                    'arrival_date' => Carbon::createFromTimestamp($payout->arrival_date)->format('Y-m-d'),
                    'created' => Carbon::createFromTimestamp($payout->created)->format('Y-m-d H:i:s'),
                    'description' => $payout->description
                ];
            }

            return response()->json([
                'success' => true,
                'payouts' => $payoutHistory
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve payout history', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payout history'
            ], 500);
        }
    }
}
