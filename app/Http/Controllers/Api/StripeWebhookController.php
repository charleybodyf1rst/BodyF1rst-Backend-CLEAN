<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Models\Subscription;
use App\Models\PaymentMethod;
use App\Models\Invoice;
use Carbon\Carbon;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController extends Controller
{
    /**
     * Handle Stripe webhooks
     * IMPORTANT: This endpoint must be excluded from CSRF protection
     */
    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = env('STRIPE_WEBHOOK_SECRET');

        try {
            // Verify webhook signature
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            Log::error('Stripe webhook invalid payload', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (SignatureVerificationException $e) {
            // Invalid signature
            Log::error('Stripe webhook invalid signature', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // Handle the event
        try {
            switch ($event->type) {
                case 'payment_intent.succeeded':
                    $this->handlePaymentIntentSucceeded($event->data->object);
                    break;

                case 'payment_intent.payment_failed':
                    $this->handlePaymentIntentFailed($event->data->object);
                    break;

                case 'customer.subscription.created':
                    $this->handleSubscriptionCreated($event->data->object);
                    break;

                case 'customer.subscription.updated':
                    $this->handleSubscriptionUpdated($event->data->object);
                    break;

                case 'customer.subscription.deleted':
                    $this->handleSubscriptionDeleted($event->data->object);
                    break;

                case 'invoice.payment_succeeded':
                    $this->handleInvoicePaymentSucceeded($event->data->object);
                    break;

                case 'invoice.payment_failed':
                    $this->handleInvoicePaymentFailed($event->data->object);
                    break;

                case 'setup_intent.succeeded':
                    $this->handleSetupIntentSucceeded($event->data->object);
                    break;

                case 'payment_method.attached':
                    $this->handlePaymentMethodAttached($event->data->object);
                    break;

                case 'payment_method.detached':
                    $this->handlePaymentMethodDetached($event->data->object);
                    break;

                default:
                    Log::info('Unhandled Stripe webhook event', ['type' => $event->type]);
            }

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('Stripe webhook handling error', [
                'type' => $event->type,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Webhook handling failed'], 500);
        }
    }

    /**
     * Handle successful payment intent
     */
    private function handlePaymentIntentSucceeded($paymentIntent)
    {
        Log::info('Payment intent succeeded', ['payment_intent_id' => $paymentIntent->id]);

        // Find user by customer ID
        $user = User::where('stripe_customer_id', $paymentIntent->customer)->first();

        if (!$user) {
            Log::warning('User not found for payment intent', ['customer_id' => $paymentIntent->customer]);
            return;
        }

        // Update or create payment record
        DB::table('payments')->updateOrInsert(
            ['transaction_id' => $paymentIntent->id],
            [
                'user_id' => $user->id,
                'amount' => $paymentIntent->amount / 100, // Convert from cents
                'currency' => strtoupper($paymentIntent->currency),
                'status' => 'completed',
                'payment_method' => $paymentIntent->payment_method_types[0] ?? 'card',
                'payment_date' => now(),
                'updated_at' => now()
            ]
        );
    }

    /**
     * Handle failed payment intent
     */
    private function handlePaymentIntentFailed($paymentIntent)
    {
        Log::warning('Payment intent failed', [
            'payment_intent_id' => $paymentIntent->id,
            'error' => $paymentIntent->last_payment_error->message ?? 'Unknown error'
        ]);

        // Find user by customer ID
        $user = User::where('stripe_customer_id', $paymentIntent->customer)->first();

        if (!$user) {
            return;
        }

        // Update payment record
        DB::table('payments')->updateOrInsert(
            ['transaction_id' => $paymentIntent->id],
            [
                'user_id' => $user->id,
                'amount' => $paymentIntent->amount / 100,
                'currency' => strtoupper($paymentIntent->currency),
                'status' => 'failed',
                'payment_method' => $paymentIntent->payment_method_types[0] ?? 'card',
                'updated_at' => now()
            ]
        );

        // Send payment failed email
        Mail::send('emails.payment_failed', [
            'user' => $user,
            'amount' => $paymentIntent->amount / 100,
            'error' => $paymentIntent->last_payment_error->message ?? 'Unknown error'
        ], function ($message) use ($user) {
            $message->to($user->email)
                ->subject('Payment Failed - BodyF1rst');
        });
    }

    /**
     * Handle subscription created
     */
    private function handleSubscriptionCreated($subscription)
    {
        Log::info('Subscription created', ['subscription_id' => $subscription->id]);

        $user = User::where('stripe_customer_id', $subscription->customer)->first();

        if (!$user) {
            return;
        }

        // Create or update subscription record
        Subscription::updateOrCreate(
            ['stripe_subscription_id' => $subscription->id],
            [
                'user_id' => $user->id,
                'plan_id' => $subscription->items->data[0]->price->id ?? null,
                'status' => $subscription->status,
                'stripe_subscription_id' => $subscription->id,
                'current_period_start' => Carbon::createFromTimestamp($subscription->current_period_start),
                'current_period_end' => Carbon::createFromTimestamp($subscription->current_period_end),
                'cancel_at_period_end' => $subscription->cancel_at_period_end
            ]
        );
    }

    /**
     * Handle subscription updated
     */
    private function handleSubscriptionUpdated($subscription)
    {
        Log::info('Subscription updated', ['subscription_id' => $subscription->id]);

        Subscription::where('stripe_subscription_id', $subscription->id)->update([
            'status' => $subscription->status,
            'current_period_start' => Carbon::createFromTimestamp($subscription->current_period_start),
            'current_period_end' => Carbon::createFromTimestamp($subscription->current_period_end),
            'cancel_at_period_end' => $subscription->cancel_at_period_end
        ]);
    }

    /**
     * Handle subscription deleted/cancelled
     */
    private function handleSubscriptionDeleted($subscription)
    {
        Log::info('Subscription deleted', ['subscription_id' => $subscription->id]);

        Subscription::where('stripe_subscription_id', $subscription->id)->update([
            'status' => 'cancelled',
            'cancelled_at' => now()
        ]);

        // Find user and update status
        $user = User::where('stripe_customer_id', $subscription->customer)->first();
        if ($user) {
            $user->update([
                'subscription_status' => 'cancelled',
                'subscription_expires_at' => null
            ]);
        }
    }

    /**
     * Handle successful invoice payment
     */
    private function handleInvoicePaymentSucceeded($invoice)
    {
        Log::info('Invoice payment succeeded', ['invoice_id' => $invoice->id]);

        $user = User::where('stripe_customer_id', $invoice->customer)->first();

        if (!$user) {
            return;
        }

        // Create or update invoice
        Invoice::updateOrCreate(
            ['stripe_invoice_id' => $invoice->id],
            [
                'user_id' => $user->id,
                'invoice_number' => $invoice->number,
                'amount' => $invoice->amount_paid / 100,
                'currency' => strtoupper($invoice->currency),
                'status' => 'paid',
                'invoice_date' => Carbon::createFromTimestamp($invoice->created),
                'paid_at' => now(),
                'stripe_invoice_id' => $invoice->id,
                'invoice_pdf' => $invoice->invoice_pdf
            ]
        );

        // Send invoice email
        Mail::send('emails.invoice_paid', [
            'user' => $user,
            'invoice' => $invoice
        ], function ($message) use ($user, $invoice) {
            $message->to($user->email)
                ->subject("Invoice #{$invoice->number} - BodyF1rst");
        });
    }

    /**
     * Handle failed invoice payment
     */
    private function handleInvoicePaymentFailed($invoice)
    {
        Log::warning('Invoice payment failed', ['invoice_id' => $invoice->id]);

        $user = User::where('stripe_customer_id', $invoice->customer)->first();

        if (!$user) {
            return;
        }

        // Update invoice status
        Invoice::updateOrCreate(
            ['stripe_invoice_id' => $invoice->id],
            [
                'user_id' => $user->id,
                'invoice_number' => $invoice->number,
                'amount' => $invoice->amount_due / 100,
                'currency' => strtoupper($invoice->currency),
                'status' => 'failed',
                'invoice_date' => Carbon::createFromTimestamp($invoice->created),
                'stripe_invoice_id' => $invoice->id
            ]
        );

        // Send payment failed email
        Mail::send('emails.invoice_payment_failed', [
            'user' => $user,
            'invoice' => $invoice
        ], function ($message) use ($user, $invoice) {
            $message->to($user->email)
                ->subject("Payment Failed for Invoice #{$invoice->number}");
        });
    }

    /**
     * Handle setup intent succeeded
     */
    private function handleSetupIntentSucceeded($setupIntent)
    {
        Log::info('Setup intent succeeded', ['setup_intent_id' => $setupIntent->id]);

        // Payment method is attached via confirmPaymentMethod endpoint
        // This webhook is just for logging
    }

    /**
     * Handle payment method attached
     */
    private function handlePaymentMethodAttached($paymentMethod)
    {
        Log::info('Payment method attached', ['payment_method_id' => $paymentMethod->id]);

        // Payment method is saved via confirmPaymentMethod endpoint
        // This webhook is just for logging
    }

    /**
     * Handle payment method detached
     */
    private function handlePaymentMethodDetached($paymentMethod)
    {
        Log::info('Payment method detached', ['payment_method_id' => $paymentMethod->id]);

        // Delete from database
        PaymentMethod::where('stripe_payment_method_id', $paymentMethod->id)->delete();
    }
}
