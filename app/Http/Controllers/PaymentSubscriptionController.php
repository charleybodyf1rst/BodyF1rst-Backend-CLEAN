<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Subscription;
use App\Models\Payment;
use App\Models\Invoice;
use Carbon\Carbon;

class PaymentSubscriptionController extends Controller
{
    /**
     * Get available subscription plans
     */
    public function getSubscriptionPlans(Request $request)
    {
        try {
            $validated = $request->validate([
                'plan_type' => 'nullable|string|in:individual,coach,organization',
                'billing_cycle' => 'nullable|string|in:monthly,yearly'
            ]);

            $query = DB::table('subscription_plans')
                ->where('is_active', true);

            if (!empty($validated['plan_type'])) {
                $query->where('plan_type', $validated['plan_type']);
            }

            if (!empty($validated['billing_cycle'])) {
                $query->where('billing_cycle', $validated['billing_cycle']);
            }

            $plans = $query->orderBy('price', 'asc')->get();

            // Add features and pricing details
            foreach ($plans as $plan) {
                $plan->features = json_decode($plan->features ?? '[]', true);
                $plan->limits = json_decode($plan->limits ?? '{}', true);
                $plan->savings = $plan->billing_cycle === 'yearly'
                    ? round((1 - ($plan->price / ($plan->monthly_equivalent * 12))) * 100, 0)
                    : 0;
            }

            return response()->json([
                'success' => true,
                'plans' => $plans
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching subscription plans', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch subscription plans',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Subscribe to a plan
     */
    public function subscribe(Request $request)
    {
        try {
            $validated = $request->validate([
                'plan_id' => 'required|integer|exists:subscription_plans,id',
                'payment_method' => 'required|string|in:card,paypal,stripe,apple_pay,google_pay',
                'payment_token' => 'required|string',
                'billing_cycle' => 'required|string|in:monthly,yearly',
                'auto_renew' => 'nullable|boolean',
                'coupon_code' => 'nullable|string'
            ]);

            DB::beginTransaction();

            $userId = Auth::id();
            $user = User::findOrFail($userId);

            // Get plan details
            $plan = DB::table('subscription_plans')->where('id', $validated['plan_id'])->first();
            if (!$plan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription plan not found'
                ], 404);
            }

            // Check for existing active subscription
            $existingSub = Subscription::where('user_id', $userId)
                ->where('status', 'active')
                ->first();

            if ($existingSub) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have an active subscription. Please cancel it first or upgrade.'
                ], 400);
            }

            // Apply coupon if provided
            $discount = 0;
            $couponId = null;
            if (!empty($validated['coupon_code'])) {
                $couponResult = $this->applyCoupon($validated['coupon_code'], $plan->price);
                if ($couponResult['valid']) {
                    $discount = $couponResult['discount'];
                    $couponId = $couponResult['coupon_id'];
                }
            }

            $finalAmount = $plan->price - $discount;

            // Process payment
            $paymentResult = $this->processPayment([
                'amount' => $finalAmount,
                'currency' => $plan->currency ?? 'USD',
                'payment_method' => $validated['payment_method'],
                'payment_token' => $validated['payment_token'],
                'description' => "Subscription: {$plan->name}",
                'user_id' => $userId
            ]);

            if (!$paymentResult['success']) {
                DB::rollback();
                return response()->json([
                    'success' => false,
                    'message' => 'Payment failed: ' . $paymentResult['message']
                ], 400);
            }

            // Create subscription
            $startDate = Carbon::now();
            $endDate = $validated['billing_cycle'] === 'yearly'
                ? $startDate->copy()->addYear()
                : $startDate->copy()->addMonth();

            $subscription = Subscription::create([
                'user_id' => $userId,
                'plan_id' => $validated['plan_id'],
                'status' => 'active',
                'billing_cycle' => $validated['billing_cycle'],
                'amount' => $finalAmount,
                'currency' => $plan->currency ?? 'USD',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'next_billing_date' => $endDate,
                'auto_renew' => $validated['auto_renew'] ?? true,
                'payment_method' => $validated['payment_method'],
                'coupon_id' => $couponId
            ]);

            // Record payment
            Payment::create([
                'user_id' => $userId,
                'subscription_id' => $subscription->id,
                'amount' => $finalAmount,
                'currency' => $plan->currency ?? 'USD',
                'payment_method' => $validated['payment_method'],
                'transaction_id' => $paymentResult['transaction_id'],
                'status' => 'completed',
                'payment_date' => now()
            ]);

            // Generate invoice
            $invoice = $this->generateInvoice($subscription, $plan, $discount);

            // Update user subscription status
            $user->update([
                'subscription_status' => 'active',
                'subscription_plan_id' => $plan->id,
                'subscription_expires_at' => $endDate
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Subscription activated successfully',
                'subscription' => $subscription,
                'invoice' => $invoice
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error subscribing', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current subscription
     */
    public function getCurrentSubscription(Request $request)
    {
        try {
            $userId = Auth::id();

            $subscription = Subscription::where('user_id', $userId)
                ->with(['plan', 'payments'])
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$subscription) {
                return response()->json([
                    'success' => true,
                    'subscription' => null,
                    'message' => 'No active subscription'
                ]);
            }

            // Get plan details
            $plan = DB::table('subscription_plans')->where('id', $subscription->plan_id)->first();

            // Calculate usage stats
            $usageStats = $this->calculateUsageStats($userId, $subscription);

            return response()->json([
                'success' => true,
                'subscription' => [
                    'id' => $subscription->id,
                    'status' => $subscription->status,
                    'plan' => [
                        'name' => $plan->name,
                        'type' => $plan->plan_type,
                        'features' => json_decode($plan->features ?? '[]', true)
                    ],
                    'billing' => [
                        'amount' => $subscription->amount,
                        'currency' => $subscription->currency,
                        'cycle' => $subscription->billing_cycle,
                        'next_billing_date' => $subscription->next_billing_date,
                        'auto_renew' => $subscription->auto_renew
                    ],
                    'dates' => [
                        'started' => $subscription->start_date,
                        'expires' => $subscription->end_date,
                        'days_remaining' => Carbon::parse($subscription->end_date)->diffInDays(Carbon::now())
                    ],
                    'usage' => $usageStats
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching subscription', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription(Request $request)
    {
        try {
            $validated = $request->validate([
                'reason' => 'nullable|string|max:500',
                'feedback' => 'nullable|string|max:1000',
                'cancel_immediately' => 'nullable|boolean'
            ]);

            DB::beginTransaction();

            $userId = Auth::id();

            $subscription = Subscription::where('user_id', $userId)
                ->where('status', 'active')
                ->firstOrFail();

            if ($validated['cancel_immediately'] ?? false) {
                // Cancel immediately
                $subscription->status = 'cancelled';
                $subscription->cancelled_at = now();
                $subscription->end_date = now();
            } else {
                // Cancel at end of billing period
                $subscription->status = 'pending_cancellation';
                $subscription->auto_renew = false;
                $subscription->cancellation_scheduled_for = $subscription->end_date;
            }

            $subscription->cancellation_reason = $validated['reason'] ?? null;
            $subscription->cancellation_feedback = $validated['feedback'] ?? null;
            $subscription->save();

            // Update user status if cancelled immediately
            if ($validated['cancel_immediately'] ?? false) {
                User::where('id', $userId)->update([
                    'subscription_status' => 'cancelled',
                    'subscription_expires_at' => null
                ]);
            }

            // Log cancellation
            DB::table('subscription_logs')->insert([
                'subscription_id' => $subscription->id,
                'user_id' => $userId,
                'action' => 'cancelled',
                'details' => json_encode($validated),
                'created_at' => now()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => ($validated['cancel_immediately'] ?? false)
                    ? 'Subscription cancelled immediately'
                    : 'Subscription will be cancelled at the end of current billing period',
                'subscription' => $subscription
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error cancelling subscription', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upgrade/downgrade subscription
     */
    public function changeSubscription(Request $request)
    {
        try {
            $validated = $request->validate([
                'new_plan_id' => 'required|integer|exists:subscription_plans,id',
                'prorate' => 'nullable|boolean'
            ]);

            DB::beginTransaction();

            $userId = Auth::id();

            $currentSub = Subscription::where('user_id', $userId)
                ->where('status', 'active')
                ->firstOrFail();

            $newPlan = DB::table('subscription_plans')->where('id', $validated['new_plan_id'])->first();
            $oldPlan = DB::table('subscription_plans')->where('id', $currentSub->plan_id)->first();

            // Calculate proration if requested
            $prorationAmount = 0;
            if ($validated['prorate'] ?? true) {
                $daysRemaining = Carbon::parse($currentSub->end_date)->diffInDays(Carbon::now());
                $totalDays = Carbon::parse($currentSub->start_date)->diffInDays(Carbon::parse($currentSub->end_date));
                $unusedAmount = ($currentSub->amount / $totalDays) * $daysRemaining;
                $prorationAmount = max(0, $newPlan->price - $unusedAmount);
            } else {
                $prorationAmount = $newPlan->price;
            }

            // Process payment for difference if upgrading
            if ($newPlan->price > $oldPlan->price && $prorationAmount > 0) {
                $paymentResult = $this->processPayment([
                    'amount' => $prorationAmount,
                    'currency' => $newPlan->currency ?? 'USD',
                    'payment_method' => $currentSub->payment_method,
                    'description' => "Subscription upgrade: {$newPlan->name}",
                    'user_id' => $userId
                ]);

                if (!$paymentResult['success']) {
                    DB::rollback();
                    return response()->json([
                        'success' => false,
                        'message' => 'Payment failed: ' . $paymentResult['message']
                    ], 400);
                }

                // Record payment
                Payment::create([
                    'user_id' => $userId,
                    'subscription_id' => $currentSub->id,
                    'amount' => $prorationAmount,
                    'currency' => $newPlan->currency ?? 'USD',
                    'payment_method' => $currentSub->payment_method,
                    'transaction_id' => $paymentResult['transaction_id'],
                    'status' => 'completed',
                    'payment_date' => now(),
                    'payment_type' => 'upgrade'
                ]);
            }

            // Update subscription
            $currentSub->plan_id = $validated['new_plan_id'];
            $currentSub->amount = $newPlan->price;
            $currentSub->save();

            // Update user
            User::where('id', $userId)->update([
                'subscription_plan_id' => $newPlan->id
            ]);

            // Log change
            DB::table('subscription_logs')->insert([
                'subscription_id' => $currentSub->id,
                'user_id' => $userId,
                'action' => 'plan_changed',
                'details' => json_encode([
                    'old_plan_id' => $oldPlan->id,
                    'new_plan_id' => $newPlan->id,
                    'proration_amount' => $prorationAmount
                ]),
                'created_at' => now()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Subscription plan changed successfully',
                'subscription' => $currentSub,
                'proration_amount' => $prorationAmount
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error changing subscription', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to change subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment history
     */
    public function getPaymentHistory(Request $request)
    {
        try {
            $validated = $request->validate([
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date',
                'status' => 'nullable|string|in:completed,pending,failed,refunded',
                'page' => 'nullable|integer|min:1',
                'limit' => 'nullable|integer|min:1|max:100'
            ]);

            $userId = Auth::id();
            $query = Payment::where('user_id', $userId);

            if (!empty($validated['start_date'])) {
                $query->where('payment_date', '>=', $validated['start_date']);
            }

            if (!empty($validated['end_date'])) {
                $query->where('payment_date', '<=', $validated['end_date']);
            }

            if (!empty($validated['status'])) {
                $query->where('status', $validated['status']);
            }

            $query->orderBy('payment_date', 'desc');

            $limit = $validated['limit'] ?? 20;
            $payments = $query->paginate($limit);

            // Calculate totals
            $totals = [
                'total_paid' => Payment::where('user_id', $userId)
                    ->where('status', 'completed')->sum('amount'),
                'total_refunded' => Payment::where('user_id', $userId)
                    ->where('status', 'refunded')->sum('amount'),
                'total_transactions' => Payment::where('user_id', $userId)->count()
            ];

            return response()->json([
                'success' => true,
                'payments' => $payments,
                'totals' => $totals
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching payment history', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payment history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get invoices
     */
    public function getInvoices(Request $request)
    {
        try {
            $validated = $request->validate([
                'year' => 'nullable|integer|min:2020|max:2099',
                'status' => 'nullable|string|in:paid,pending,overdue',
                'page' => 'nullable|integer|min:1',
                'limit' => 'nullable|integer|min:1|max:100'
            ]);

            $userId = Auth::id();
            $query = Invoice::where('user_id', $userId);

            if (!empty($validated['year'])) {
                $query->whereYear('invoice_date', $validated['year']);
            }

            if (!empty($validated['status'])) {
                $query->where('status', $validated['status']);
            }

            $query->orderBy('invoice_date', 'desc');

            $limit = $validated['limit'] ?? 20;
            $invoices = $query->paginate($limit);

            return response()->json([
                'success' => true,
                'invoices' => $invoices
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching invoices', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch invoices',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download invoice
     */
    public function downloadInvoice($invoiceId)
    {
        try {
            $userId = Auth::id();

            $invoice = Invoice::where('id', $invoiceId)
                ->where('user_id', $userId)
                ->firstOrFail();

            // Generate PDF (implement your PDF generation logic)
            $pdfContent = $this->generateInvoicePDF($invoice);

            return response($pdfContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', "attachment; filename=invoice-{$invoice->invoice_number}.pdf");

        } catch (\Exception $e) {
            Log::error('Error downloading invoice', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to download invoice',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Apply coupon code
     */
    public function validateCoupon(Request $request)
    {
        try {
            $validated = $request->validate([
                'coupon_code' => 'required|string',
                'plan_id' => 'required|integer|exists:subscription_plans,id'
            ]);

            $plan = DB::table('subscription_plans')->where('id', $validated['plan_id'])->first();

            $couponResult = $this->applyCoupon($validated['coupon_code'], $plan->price);

            if (!$couponResult['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => $couponResult['message']
                ], 400);
            }

            return response()->json([
                'success' => true,
                'valid' => true,
                'discount' => $couponResult['discount'],
                'discount_type' => $couponResult['discount_type'],
                'final_amount' => $plan->price - $couponResult['discount'],
                'message' => 'Coupon applied successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error validating coupon', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to validate coupon',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update payment method
     */
    public function updatePaymentMethod(Request $request)
    {
        try {
            $validated = $request->validate([
                'payment_method' => 'required|string|in:card,paypal,stripe,apple_pay,google_pay',
                'payment_token' => 'required|string'
            ]);

            DB::beginTransaction();

            $userId = Auth::id();

            $subscription = Subscription::where('user_id', $userId)
                ->where('status', 'active')
                ->firstOrFail();

            // Verify payment method with provider
            $verificationResult = $this->verifyPaymentMethod($validated['payment_method'], $validated['payment_token']);

            if (!$verificationResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment method verification failed'
                ], 400);
            }

            // Update subscription
            $subscription->payment_method = $validated['payment_method'];
            $subscription->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment method updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error updating payment method', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment method',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Request refund
     */
    public function requestRefund(Request $request)
    {
        try {
            $validated = $request->validate([
                'payment_id' => 'required|integer|exists:payments,id',
                'reason' => 'required|string|max:500',
                'amount' => 'nullable|numeric|min:0'
            ]);

            DB::beginTransaction();

            $userId = Auth::id();

            $payment = Payment::where('id', $validated['payment_id'])
                ->where('user_id', $userId)
                ->where('status', 'completed')
                ->firstOrFail();

            // Check refund eligibility (e.g., within 30 days)
            $daysSincePayment = Carbon::parse($payment->payment_date)->diffInDays(Carbon::now());
            if ($daysSincePayment > 30) {
                return response()->json([
                    'success' => false,
                    'message' => 'Refund period has expired. Refunds are only available within 30 days of payment.'
                ], 400);
            }

            // Create refund request
            $refundAmount = $validated['amount'] ?? $payment->amount;

            DB::table('refund_requests')->insert([
                'payment_id' => $payment->id,
                'user_id' => $userId,
                'amount' => $refundAmount,
                'reason' => $validated['reason'],
                'status' => 'pending',
                'requested_at' => now(),
                'created_at' => now()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Refund request submitted successfully. We will review your request within 3-5 business days.'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error requesting refund', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit refund request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Private helper methods
     */
    private function processPayment($data)
    {
        // Implement actual payment processing with Stripe, PayPal, etc.
        // This is a placeholder
        try {
            // Simulate payment processing
            return [
                'success' => true,
                'transaction_id' => 'txn_' . uniqid(),
                'message' => 'Payment processed successfully'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    private function applyCoupon($code, $amount)
    {
        $coupon = DB::table('coupons')
            ->where('code', $code)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->first();

        if (!$coupon) {
            return [
                'valid' => false,
                'message' => 'Invalid or expired coupon code'
            ];
        }

        // Check usage limit
        if ($coupon->usage_limit) {
            $usageCount = DB::table('coupon_usage')->where('coupon_id', $coupon->id)->count();
            if ($usageCount >= $coupon->usage_limit) {
                return [
                    'valid' => false,
                    'message' => 'Coupon usage limit reached'
                ];
            }
        }

        // Calculate discount
        $discount = 0;
        if ($coupon->discount_type === 'percentage') {
            $discount = ($amount * $coupon->discount_value) / 100;
            if ($coupon->max_discount) {
                $discount = min($discount, $coupon->max_discount);
            }
        } else {
            $discount = $coupon->discount_value;
        }

        return [
            'valid' => true,
            'discount' => $discount,
            'discount_type' => $coupon->discount_type,
            'coupon_id' => $coupon->id
        ];
    }

    private function generateInvoice($subscription, $plan, $discount)
    {
        $invoiceNumber = 'INV-' . date('Ymd') . '-' . $subscription->id;

        $invoice = Invoice::create([
            'user_id' => $subscription->user_id,
            'subscription_id' => $subscription->id,
            'invoice_number' => $invoiceNumber,
            'invoice_date' => now(),
            'due_date' => now()->addDays(7),
            'subtotal' => $plan->price,
            'discount' => $discount,
            'total' => $subscription->amount,
            'currency' => $subscription->currency,
            'status' => 'paid',
            'paid_at' => now()
        ]);

        return $invoice;
    }

    private function generateInvoicePDF($invoice)
    {
        // Implement PDF generation logic
        return '';
    }

    private function calculateUsageStats($userId, $subscription)
    {
        // Calculate usage based on plan limits
        return [
            'workouts_used' => DB::table('workout_sessions')
                ->where('user_id', $userId)
                ->whereBetween('created_at', [$subscription->start_date, now()])
                ->count(),
            'storage_used_mb' => 0, // Implement storage calculation
            'clients_count' => DB::table('coach_client_relationships')
                ->where('coach_id', $userId)
                ->count()
        ];
    }

    private function verifyPaymentMethod($method, $token)
    {
        // Implement payment method verification
        return ['success' => true];
    }
}