<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Models\Subscription;
use App\Models\PaymentMethod;
use App\Models\Invoice;
use Carbon\Carbon;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\PaymentIntent;
use Stripe\SetupIntent;
use Stripe\PaymentMethod as StripePaymentMethod;
use Stripe\Subscription as StripeSubscription;

class StripePaymentController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(env('STRIPE_SECRET_KEY'));
    }

    /**
     * Create Stripe setup intent for adding payment method
     */
    public function createSetupIntent(Request $request)
    {
        try {
            $userId = Auth::id();
            $user = User::findOrFail($userId);

            // Get or create Stripe customer
            $stripeCustomerId = $this->getOrCreateStripeCustomer($user);

            // Create setup intent
            $setupIntent = SetupIntent::create([
                'customer' => $stripeCustomerId,
                'payment_method_types' => ['card', 'us_bank_account'],
                'metadata' => [
                    'user_id' => $userId
                ]
            ]);

            return response()->json([
                'success' => true,
                'clientSecret' => $setupIntent->client_secret,
                'customerId' => $stripeCustomerId
            ]);

        } catch (\Exception $e) {
            Log::error('Error creating setup intent', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create setup intent',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add payment method after Stripe.js confirmation
     */
    public function addPaymentMethod(Request $request)
    {
        try {
            $validated = $request->validate([
                'payment_method_id' => 'required|string'
            ]);

            $userId = Auth::id();
            $user = User::findOrFail($userId);

            // Get Stripe customer
            $stripeCustomerId = $this->getOrCreateStripeCustomer($user);

            // Attach payment method to customer
            $stripePaymentMethod = StripePaymentMethod::retrieve($validated['payment_method_id']);
            $stripePaymentMethod->attach(['customer' => $stripeCustomerId]);

            // Get payment method details
            $type = $stripePaymentMethod->type;
            $last4 = $type === 'card' ? $stripePaymentMethod->card->last4 : $stripePaymentMethod->us_bank_account->last4;
            $brand = $type === 'card' ? $stripePaymentMethod->card->brand : 'Bank Account';

            // Check if this is the first payment method (make it default)
            $isFirst = PaymentMethod::where('user_id', $userId)->count() === 0;

            // Save to database
            $paymentMethod = PaymentMethod::create([
                'user_id' => $userId,
                'stripe_payment_method_id' => $validated['payment_method_id'],
                'type' => $type,
                'brand' => $brand,
                'last4' => $last4,
                'is_default' => $isFirst
            ]);

            // Set as default on Stripe if first
            if ($isFirst) {
                Customer::update($stripeCustomerId, [
                    'invoice_settings' => [
                        'default_payment_method' => $validated['payment_method_id']
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment method added successfully',
                'payment_method' => $paymentMethod
            ]);

        } catch (\Exception $e) {
            Log::error('Error adding payment method', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to add payment method',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all payment methods for user
     */
    public function getPaymentMethods(Request $request)
    {
        try {
            $userId = Auth::id();

            $paymentMethods = PaymentMethod::where('user_id', $userId)
                ->orderBy('is_default', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'payment_methods' => $paymentMethods
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching payment methods', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payment methods',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set default payment method
     */
    public function setDefaultPaymentMethod(Request $request, $methodId)
    {
        try {
            $userId = Auth::id();
            $user = User::findOrFail($userId);

            $paymentMethod = PaymentMethod::where('id', $methodId)
                ->where('user_id', $userId)
                ->firstOrFail();

            DB::beginTransaction();

            // Remove default from all others
            PaymentMethod::where('user_id', $userId)->update(['is_default' => false]);

            // Set new default
            $paymentMethod->is_default = true;
            $paymentMethod->save();

            // Update Stripe customer
            $stripeCustomerId = $user->stripe_customer_id;
            if ($stripeCustomerId) {
                Customer::update($stripeCustomerId, [
                    'invoice_settings' => [
                        'default_payment_method' => $paymentMethod->stripe_payment_method_id
                    ]
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Default payment method updated'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error setting default payment method', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to set default payment method',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete payment method
     */
    public function deletePaymentMethod(Request $request, $methodId)
    {
        try {
            $userId = Auth::id();

            $paymentMethod = PaymentMethod::where('id', $methodId)
                ->where('user_id', $userId)
                ->firstOrFail();

            if ($paymentMethod->is_default) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete default payment method. Set another as default first.'
                ], 400);
            }

            DB::beginTransaction();

            // Detach from Stripe
            try {
                StripePaymentMethod::retrieve($paymentMethod->stripe_payment_method_id)->detach();
            } catch (\Exception $e) {
                Log::warning('Stripe detach failed (might already be detached)', ['error' => $e->getMessage()]);
            }

            // Delete from database
            $paymentMethod->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment method deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error deleting payment method', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete payment method',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate surcharge for amount
     */
    public function calculateSurcharge(Request $request)
    {
        try {
            $validated = $request->validate([
                'amount' => 'required|numeric|min:0',
                'payment_method_type' => 'required|string|in:card,bank_account',
                'card_type' => 'nullable|string|in:credit,debit',
                'state_code' => 'nullable|string|size:2'
            ]);

            $amount = $validated['amount'];
            $paymentMethodType = $validated['payment_method_type'];
            $cardType = $validated['card_type'] ?? 'credit';
            $stateCode = $validated['state_code'] ?? null;

            // Check if surcharging is enabled
            $surchargeEnabled = env('SURCHARGE_ENABLED', true);

            // Restricted states (CT, MA)
            $restrictedStates = ['CT', 'MA'];
            if ($stateCode && in_array(strtoupper($stateCode), $restrictedStates)) {
                $surchargeEnabled = false;
            }

            $surchargeAmount = 0;
            if ($surchargeEnabled && $paymentMethodType === 'card' && $cardType === 'credit') {
                // Credit card: 2.9% + $0.30
                $rate = env('SURCHARGE_CREDIT_CARD_RATE', 0.029);
                $fixed = env('SURCHARGE_CREDIT_CARD_FIXED', 0.30);

                $surchargeAmount = ($amount * $rate) + $fixed;
                $surchargeAmount = round($surchargeAmount, 2);
            }

            $totalAmount = round($amount + $surchargeAmount, 2);

            return response()->json([
                'success' => true,
                'original_amount' => $amount,
                'surcharge_amount' => $surchargeAmount,
                'total_amount' => $totalAmount,
                'surcharge_enabled' => $surchargeEnabled,
                'payment_method_type' => $paymentMethodType
            ]);

        } catch (\Exception $e) {
            Log::error('Error calculating surcharge', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate surcharge',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get surcharge configuration
     */
    public function getSurchargeConfig(Request $request)
    {
        try {
            $config = [
                'enabled' => env('SURCHARGE_ENABLED', true),
                'credit_card_rate' => env('SURCHARGE_CREDIT_CARD_RATE', 0.029),
                'credit_card_fixed' => env('SURCHARGE_CREDIT_CARD_FIXED', 0.30),
                'debit_card_rate' => env('SURCHARGE_DEBIT_CARD_RATE', 0),
                'debit_card_fixed' => env('SURCHARGE_DEBIT_CARD_FIXED', 0),
                'display_name' => 'Processing Fee',
                'restricted_states' => ['CT', 'MA']
            ];

            return response()->json([
                'success' => true,
                'config' => $config
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting surcharge config', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to get surcharge config',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Email invoice to user (non-admins don't get document storage)
     */
    public function emailInvoice(Request $request, $invoiceId)
    {
        try {
            $userId = Auth::id();
            $user = User::findOrFail($userId);

            $invoice = Invoice::where('id', $invoiceId)
                ->where('user_id', $userId)
                ->firstOrFail();

            // Generate PDF
            $pdfContent = $this->generateInvoicePDF($invoice);

            // Email invoice
            Mail::send('emails.invoice', ['invoice' => $invoice, 'user' => $user], function ($message) use ($user, $invoice, $pdfContent) {
                $message->to($user->email)
                    ->subject("Invoice #{$invoice->invoice_number} from BodyF1rst")
                    ->attachData($pdfContent, "invoice-{$invoice->invoice_number}.pdf", [
                        'mime' => 'application/pdf'
                    ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Invoice emailed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error emailing invoice', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to email invoice',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper: Get or create Stripe customer
     */
    private function getOrCreateStripeCustomer($user)
    {
        if ($user->stripe_customer_id) {
            return $user->stripe_customer_id;
        }

        $customer = Customer::create([
            'email' => $user->email,
            'name' => $user->name,
            'metadata' => [
                'user_id' => $user->id
            ]
        ]);

        $user->update(['stripe_customer_id' => $customer->id]);

        return $customer->id;
    }

    /**
     * Helper: Generate invoice PDF
     *
     * NOTE: This method is not needed since Stripe provides invoice PDFs automatically.
     * See downloadInvoicePDF() method which uses Stripe's built-in PDF generation.
     *
     * If custom PDF generation is needed in the future:
     * 1. Enable ext-gd in php.ini
     * 2. Run: composer require barryvdh/laravel-dompdf
     * 3. Implement custom PDF template using DomPDF
     */
    private function generateInvoicePDF($invoice)
    {
        // Use Stripe's built-in invoice PDF instead
        // Stripe automatically generates professional PDF invoices
        return $invoice->invoice_pdf ?? '';
    }

    /**
     * Create a new subscription
     * POST /api/billing/subscriptions
     */
    public function createSubscription(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => 'required|string',
            'payment_method_id' => 'required|string'
        ]);

        $userId = Auth::id();
        $user = User::findOrFail($userId);

        try {
            // Get or create Stripe customer
            $stripeCustomerId = $this->getOrCreateStripeCustomer($user);

            // Attach payment method to customer
            $paymentMethod = StripePaymentMethod::retrieve($validated['payment_method_id']);
            $paymentMethod->attach(['customer' => $stripeCustomerId]);

            // Set as default payment method
            Customer::update($stripeCustomerId, [
                'invoice_settings' => [
                    'default_payment_method' => $validated['payment_method_id']
                ]
            ]);

            // Create subscription in Stripe
            $stripeSubscription = StripeSubscription::create([
                'customer' => $stripeCustomerId,
                'items' => [
                    ['price' => $validated['plan_id']]
                ],
                'expand' => ['latest_invoice.payment_intent'],
                'metadata' => [
                    'user_id' => $userId
                ]
            ]);

            // Save subscription to database
            $subscription = Subscription::create([
                'user_id' => $userId,
                'stripe_subscription_id' => $stripeSubscription->id,
                'plan_id' => $validated['plan_id'],
                'status' => $stripeSubscription->status,
                'current_period_start' => Carbon::createFromTimestamp($stripeSubscription->current_period_start),
                'current_period_end' => Carbon::createFromTimestamp($stripeSubscription->current_period_end),
                'cancel_at_period_end' => $stripeSubscription->cancel_at_period_end
            ]);

            return response()->json([
                'success' => true,
                'subscription' => $subscription,
                'stripe_subscription' => $stripeSubscription
            ]);

        } catch (\Exception $e) {
            Log::error('Subscription creation failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create subscription: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current user's subscription
     * GET /api/billing/subscription
     */
    public function getSubscription()
    {
        $userId = Auth::id();

        try {
            $subscription = Subscription::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$subscription) {
                return response()->json([
                    'success' => true,
                    'subscription' => null
                ]);
            }

            // Get latest data from Stripe
            $stripeSubscription = StripeSubscription::retrieve($subscription->stripe_subscription_id);

            // Update local record
            $subscription->update([
                'status' => $stripeSubscription->status,
                'current_period_start' => Carbon::createFromTimestamp($stripeSubscription->current_period_start),
                'current_period_end' => Carbon::createFromTimestamp($stripeSubscription->current_period_end),
                'cancel_at_period_end' => $stripeSubscription->cancel_at_period_end
            ]);

            return response()->json([
                'success' => true,
                'subscription' => $subscription,
                'stripe_subscription' => $stripeSubscription
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve subscription', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve subscription'
            ], 500);
        }
    }

    /**
     * Update subscription plan
     * PUT /api/billing/subscription
     */
    public function updateSubscription(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => 'required|string'
        ]);

        $userId = Auth::id();

        try {
            $subscription = Subscription::where('user_id', $userId)
                ->where('status', 'active')
                ->firstOrFail();

            // Update subscription in Stripe
            $stripeSubscription = StripeSubscription::retrieve($subscription->stripe_subscription_id);

            $updatedSubscription = StripeSubscription::update($subscription->stripe_subscription_id, [
                'items' => [
                    [
                        'id' => $stripeSubscription->items->data[0]->id,
                        'price' => $validated['plan_id']
                    ]
                ],
                'proration_behavior' => 'create_prorations'
            ]);

            // Update local record
            $subscription->update([
                'plan_id' => $validated['plan_id'],
                'status' => $updatedSubscription->status
            ]);

            return response()->json([
                'success' => true,
                'subscription' => $subscription,
                'message' => 'Subscription updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Subscription update failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update subscription: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel subscription
     * DELETE /api/billing/subscription
     */
    public function cancelSubscription(Request $request)
    {
        $validated = $request->validate([
            'immediately' => 'boolean'
        ]);

        $userId = Auth::id();
        $immediately = $validated['immediately'] ?? false;

        try {
            $subscription = Subscription::where('user_id', $userId)
                ->where('status', 'active')
                ->firstOrFail();

            if ($immediately) {
                // Cancel immediately
                $stripeSubscription = StripeSubscription::cancel($subscription->stripe_subscription_id);

                $subscription->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancel_at_period_end' => false
                ]);

                $message = 'Subscription cancelled immediately';
            } else {
                // Cancel at period end
                $stripeSubscription = StripeSubscription::update($subscription->stripe_subscription_id, [
                    'cancel_at_period_end' => true
                ]);

                $subscription->update([
                    'cancel_at_period_end' => true
                ]);

                $message = 'Subscription will cancel at end of billing period';
            }

            return response()->json([
                'success' => true,
                'subscription' => $subscription,
                'message' => $message
            ]);

        } catch (\Exception $e) {
            Log::error('Subscription cancellation failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel subscription: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's invoices
     * GET /api/billing/invoices
     */
    public function getInvoices(Request $request)
    {
        $userId = Auth::id();
        $limit = $request->get('limit', 10);

        try {
            $invoices = Invoice::where('user_id', $userId)
                ->orderBy('invoice_date', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'invoices' => $invoices
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve invoices', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve invoices'
            ], 500);
        }
    }

    /**
     * Download invoice PDF
     * GET /api/billing/invoices/{id}/pdf
     */
    public function downloadInvoicePDF($id)
    {
        $userId = Auth::id();

        try {
            $invoice = Invoice::where('id', $id)
                ->where('user_id', $userId)
                ->firstOrFail();

            // If we have Stripe invoice PDF URL, redirect to it
            if ($invoice->invoice_pdf) {
                return redirect($invoice->invoice_pdf);
            }

            // Otherwise, retrieve from Stripe
            $stripeInvoice = StripeInvoice::retrieve($invoice->stripe_invoice_id);

            if ($stripeInvoice->invoice_pdf) {
                // Save URL for future use
                $invoice->update(['invoice_pdf' => $stripeInvoice->invoice_pdf]);
                return redirect($stripeInvoice->invoice_pdf);
            }

            return response()->json([
                'success' => false,
                'message' => 'PDF not available for this invoice'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to download invoice PDF', [
                'user_id' => $userId,
                'invoice_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to download invoice PDF'
            ], 500);
        }
    }

    /**
     * Get billing analytics
     * GET /api/billing/analytics
     */
    public function getBillingAnalytics(Request $request)
    {
        $userId = Auth::id();
        $period = $request->get('period', '30'); // Days to look back

        try {
            $startDate = Carbon::now()->subDays($period);

            // Total spent
            $totalSpent = Invoice::where('user_id', $userId)
                ->where('status', 'paid')
                ->sum('amount');

            // Spending by month
            $monthlySpending = Invoice::where('user_id', $userId)
                ->where('status', 'paid')
                ->where('invoice_date', '>=', $startDate)
                ->selectRaw('DATE_FORMAT(invoice_date, "%Y-%m") as month, SUM(amount) as total')
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            // Active subscription info
            $subscription = Subscription::where('user_id', $userId)
                ->where('status', 'active')
                ->first();

            $subscriptionInfo = null;
            if ($subscription) {
                $subscriptionInfo = [
                    'plan_id' => $subscription->plan_id,
                    'status' => $subscription->status,
                    'current_period_end' => $subscription->current_period_end->format('Y-m-d'),
                    'cancel_at_period_end' => $subscription->cancel_at_period_end,
                    'days_until_renewal' => Carbon::now()->diffInDays($subscription->current_period_end)
                ];
            }

            // Recent invoices
            $recentInvoices = Invoice::where('user_id', $userId)
                ->orderBy('invoice_date', 'desc')
                ->limit(5)
                ->get();

            // Payment method summary
            $paymentMethods = PaymentMethod::where('user_id', $userId)
                ->select('type', DB::raw('COUNT(*) as count'))
                ->groupBy('type')
                ->get();

            return response()->json([
                'success' => true,
                'analytics' => [
                    'total_spent' => round($totalSpent, 2),
                    'monthly_spending' => $monthlySpending,
                    'subscription' => $subscriptionInfo,
                    'recent_invoices' => $recentInvoices,
                    'payment_methods' => $paymentMethods,
                    'period_days' => $period
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve billing analytics', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve analytics'
            ], 500);
        }
    }

    /**
     * Get payment history with filters
     * GET /api/billing/history
     */
    public function getPaymentHistory(Request $request)
    {
        $userId = Auth::id();
        $limit = $request->get('limit', 20);
        $status = $request->get('status'); // paid, failed, pending
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        try {
            $query = Invoice::where('user_id', $userId);

            // Apply filters
            if ($status) {
                $query->where('status', $status);
            }

            if ($startDate) {
                $query->where('invoice_date', '>=', Carbon::parse($startDate));
            }

            if ($endDate) {
                $query->where('invoice_date', '<=', Carbon::parse($endDate));
            }

            $invoices = $query->orderBy('invoice_date', 'desc')
                ->paginate($limit);

            // Calculate totals
            $totals = [
                'total' => $query->sum('amount'),
                'paid' => Invoice::where('user_id', $userId)->where('status', 'paid')->sum('amount'),
                'failed' => Invoice::where('user_id', $userId)->where('status', 'failed')->sum('amount'),
                'pending' => Invoice::where('user_id', $userId)->where('status', 'pending')->sum('amount')
            ];

            return response()->json([
                'success' => true,
                'history' => $invoices,
                'totals' => $totals
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve payment history', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment history'
            ], 500);
        }
    }

    /**
     * Verify payment method
     * POST /api/billing/payment-methods/{id}/verify
     */
    public function verifyPaymentMethod($id)
    {
        $user = Auth::user();

        try {
            \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));

            $paymentMethod = \Stripe\PaymentMethod::retrieve($id);

            if ($paymentMethod->customer != $user->stripe_customer_id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $paymentMethod->id,
                    'type' => $paymentMethod->type,
                    'card' => $paymentMethod->card,
                    'verified' => true
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get subscription upgrade options
     * GET /api/billing/subscription/upgrade-options
     */
    public function getUpgradeOptions()
    {
        $user = Auth::user();
        $currentPlan = $user->subscription_plan ?? 'free';

        $plans = [
            'basic' => ['name' => 'Basic', 'price' => 9.99, 'features' => ['Feature 1', 'Feature 2']],
            'pro' => ['name' => 'Pro', 'price' => 19.99, 'features' => ['All Basic', 'Feature 3', 'Feature 4']],
            'premium' => ['name' => 'Premium', 'price' => 29.99, 'features' => ['All Pro', 'Feature 5', 'Feature 6']]
        ];

        unset($plans[$currentPlan]); // Remove current plan

        return response()->json(['success' => true, 'options' => array_values($plans)]);
    }

    /**
     * Pause subscription
     * POST /api/billing/subscription/pause
     */
    public function pauseSubscription()
    {
        $user = Auth::user();

        try {
            \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));

            $subscription = \Stripe\Subscription::retrieve($user->stripe_subscription_id);
            $subscription->pause_collection = ['behavior' => 'void'];
            $subscription->save();

            DB::table('users')->where('id', $user->id)->update([
                'subscription_status' => 'paused',
                'subscription_paused_at' => now()
            ]);

            return response()->json(['success' => true, 'message' => 'Subscription paused successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Resume subscription
     * POST /api/billing/subscription/resume
     */
    public function resumeSubscription()
    {
        $user = Auth::user();

        try {
            \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));

            $subscription = \Stripe\Subscription::retrieve($user->stripe_subscription_id);
            $subscription->pause_collection = null;
            $subscription->save();

            DB::table('users')->where('id', $user->id)->update([
                'subscription_status' => 'active',
                'subscription_paused_at' => null
            ]);

            return response()->json(['success' => true, 'message' => 'Subscription resumed successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get invoice disputes
     * GET /api/billing/invoices/{id}/disputes
     */
    public function getInvoiceDisputes($id)
    {
        try {
            \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));

            $invoice = \Stripe\Invoice::retrieve($id);

            if ($invoice->charge) {
                $charge = \Stripe\Charge::retrieve($invoice->charge);
                $disputes = $charge->dispute ? [$charge->dispute] : [];
            } else {
                $disputes = [];
            }

            return response()->json(['success' => true, 'disputes' => $disputes]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Request refund
     * POST /api/billing/refund/{id}/request
     */
    public function requestRefund($id)
    {
        $validated = request()->validate([
            'reason' => 'required|string|in:duplicate,fraudulent,requested_by_customer',
            'amount' => 'nullable|numeric'
        ]);

        try {
            \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));

            $refundData = ['charge' => $id, 'reason' => $validated['reason']];
            if (isset($validated['amount'])) {
                $refundData['amount'] = $validated['amount'] * 100; // Convert to cents
            }

            $refund = \Stripe\Refund::create($refundData);

            return response()->json(['success' => true, 'refund' => $refund]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
