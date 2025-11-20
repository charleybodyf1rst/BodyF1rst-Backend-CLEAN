<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\Invoice;
use Carbon\Carbon;

class AdminPaymentControlsController extends Controller
{
    /**
     * Get organization payment statuses for admin dashboard
     */
    public function getOrganizationPaymentStatuses(Request $request)
    {
        try {
            // Verify admin
            if (!Auth::user()->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            $organizations = DB::table('organizations as o')
                ->leftJoin('subscriptions as s', function ($join) {
                    $join->on('o.id', '=', 's.organization_id')
                        ->where('s.status', 'active');
                })
                ->leftJoin('users as u', 'o.leader_id', '=', 'u.id')
                ->select(
                    'o.id as organization_id',
                    'o.name as organization_name',
                    'o.is_active',
                    'o.member_count',
                    's.plan_id',
                    's.amount',
                    's.next_billing_date',
                    's.status as subscription_status',
                    'u.id as leader_id',
                    'u.name as leader_name'
                )
                ->get();

            $organizationStatuses = [];

            foreach ($organizations as $org) {
                // Get plan details
                $plan = DB::table('subscription_plans')->where('id', $org->plan_id)->first();

                // Calculate overdue status
                $nextPaymentDueDate = $org->next_billing_date ? Carbon::parse($org->next_billing_date) : null;
                $isOverdue = $nextPaymentDueDate && $nextPaymentDueDate->isPast();
                $daysPastDue = $isOverdue ? $nextPaymentDueDate->diffInDays(Carbon::now()) : 0;

                // Calculate amount due
                $amountDue = $isOverdue ? ($org->amount ?? 0) : 0;

                // Get last payment date from payments or invoices table
                $lastPayment = DB::table('payments')
                    ->where('organization_id', $org->organization_id)
                    ->where('status', 'completed')
                    ->orderBy('payment_date', 'desc')
                    ->first();

                // Get Stripe IDs from subscriptions table (these fields should exist)
                $subscription = DB::table('subscriptions')
                    ->where('organization_id', $org->organization_id)
                    ->where('status', 'active')
                    ->first();

                $organizationStatuses[] = [
                    'organization_id' => $org->organization_id,
                    'organization_name' => $org->organization_name,
                    'subscription_plan' => $plan->name ?? 'No Plan',
                    'member_count' => $org->member_count ?? 0,
                    'leader_id' => $org->leader_id,
                    'leader_name' => $org->leader_name ?? 'No Leader',
                    'is_paid' => !$isOverdue,
                    'last_payment_date' => $lastPayment ? Carbon::parse($lastPayment->payment_date) : null,
                    'next_payment_due_date' => $nextPaymentDueDate,
                    'amount_due' => $amountDue,
                    'is_overdue' => $isOverdue,
                    'days_past_due' => $daysPastDue,
                    'is_active' => $org->is_active,
                    'stripe_customer_id' => $subscription->stripe_customer_id ?? null,
                    'stripe_subscription_id' => $subscription->stripe_subscription_id ?? null
                ];
            }

            return response()->json([
                'success' => true,
                'organizations' => $organizationStatuses
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching organization payment statuses', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch organization payment statuses',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get coach payment statuses for admin dashboard
     */
    public function getCoachPaymentStatuses(Request $request)
    {
        try {
            // Verify admin
            if (!Auth::user()->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            $coaches = DB::table('users')
                ->where('role', 'coach')
                ->orWhere('is_coach', true)
                ->get();

            $coachStatuses = [];

            foreach ($coaches as $coach) {
                // Check Stripe Connect status
                $hasStripeConnected = !empty($coach->stripe_connect_account_id);

                // Calculate earnings
                $totalEarnings = DB::table('coach_session_payments')
                    ->where('coach_id', $coach->id)
                    ->where('status', 'completed')
                    ->sum('amount') ?? 0;

                $pendingPayouts = DB::table('coach_session_payments')
                    ->where('coach_id', $coach->id)
                    ->where('status', 'pending')
                    ->sum('amount') ?? 0;

                // Get last payment/payout date
                $lastPayout = DB::table('coach_session_payments')
                    ->where('coach_id', $coach->id)
                    ->where('status', 'completed')
                    ->orderBy('created_at', 'desc')
                    ->first();

                $coachStatuses[] = [
                    'coach_id' => $coach->id,
                    'coach_name' => $coach->name,
                    'stripe_connect_account_id' => $coach->stripe_connect_account_id,
                    'has_stripe_connected' => $hasStripeConnected,
                    'total_earnings' => $totalEarnings,
                    'pending_payouts' => $pendingPayouts,
                    'is_paid' => $hasStripeConnected,
                    'is_active' => $coach->is_active ?? true,
                    'last_payment_date' => $lastPayout ? Carbon::parse($lastPayout->created_at) : null,
                    'next_payment_due_date' => null, // Coaches don't have scheduled payments
                    'amount_due' => 0,
                    'is_overdue' => false,
                    'days_past_due' => 0
                ];
            }

            return response()->json([
                'success' => true,
                'coaches' => $coachStatuses
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching coach payment statuses', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch coach payment statuses',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle organization status (enable/disable)
     */
    public function toggleOrganizationStatus(Request $request, $organizationId)
    {
        try {
            // Verify admin
            if (!Auth::user()->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            $validated = $request->validate([
                'is_active' => 'required|boolean'
            ]);

            DB::table('organizations')
                ->where('id', $organizationId)
                ->update([
                    'is_active' => $validated['is_active'],
                    'updated_at' => now()
                ]);

            // Log admin action
            DB::table('admin_actions')->insert([
                'admin_id' => Auth::id(),
                'action' => 'toggle_organization_status',
                'target_type' => 'organization',
                'target_id' => $organizationId,
                'details' => json_encode(['is_active' => $validated['is_active']]),
                'created_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Organization status updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error toggling organization status', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle organization status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle coach status (enable/disable)
     */
    public function toggleCoachStatus(Request $request, $coachId)
    {
        try {
            // Verify admin
            if (!Auth::user()->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            $validated = $request->validate([
                'is_active' => 'required|boolean'
            ]);

            DB::table('users')
                ->where('id', $coachId)
                ->update([
                    'is_active' => $validated['is_active'],
                    'updated_at' => now()
                ]);

            // Log admin action
            DB::table('admin_actions')->insert([
                'admin_id' => Auth::id(),
                'action' => 'toggle_coach_status',
                'target_type' => 'coach',
                'target_id' => $coachId,
                'details' => json_encode(['is_active' => $validated['is_active']]),
                'created_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Coach status updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error toggling coach status', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle coach status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk toggle organizations by payment status
     */
    public function bulkToggleOrganizationsByPayment(Request $request)
    {
        try {
            // Verify admin
            if (!Auth::user()->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            $validated = $request->validate([
                'disable_unpaid' => 'required|boolean'
            ]);

            if ($validated['disable_unpaid']) {
                // Disable all overdue organizations
                $overdueOrgs = DB::table('organizations as o')
                    ->leftJoin('subscriptions as s', function ($join) {
                        $join->on('o.id', '=', 's.organization_id')
                            ->where('s.status', 'active');
                    })
                    ->where('s.next_billing_date', '<', now())
                    ->pluck('o.id');

                DB::table('organizations')
                    ->whereIn('id', $overdueOrgs)
                    ->update([
                        'is_active' => false,
                        'updated_at' => now()
                    ]);

                $count = $overdueOrgs->count();

                // Log admin action
                DB::table('admin_actions')->insert([
                    'admin_id' => Auth::id(),
                    'action' => 'bulk_disable_unpaid_organizations',
                    'target_type' => 'organization',
                    'details' => json_encode(['count' => $count, 'organization_ids' => $overdueOrgs]),
                    'created_at' => now()
                ]);

                return response()->json([
                    'success' => true,
                    'message' => "{$count} overdue organizations disabled successfully"
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No action specified'
            ], 400);

        } catch (\Exception $e) {
            Log::error('Error bulk toggling organizations', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk toggle organizations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk toggle coaches by Stripe connection status
     */
    public function bulkToggleCoachesByPayment(Request $request)
    {
        try {
            // Verify admin
            if (!Auth::user()->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            $validated = $request->validate([
                'disable_without_stripe' => 'required|boolean'
            ]);

            if ($validated['disable_without_stripe']) {
                // Disable all coaches without Stripe Connect
                $coachesWithoutStripe = DB::table('users')
                    ->where(function ($query) {
                        $query->where('role', 'coach')
                            ->orWhere('is_coach', true);
                    })
                    ->where(function ($query) {
                        $query->whereNull('stripe_connect_account_id')
                            ->orWhere('stripe_connect_account_id', '');
                    })
                    ->pluck('id');

                DB::table('users')
                    ->whereIn('id', $coachesWithoutStripe)
                    ->update([
                        'is_active' => false,
                        'updated_at' => now()
                    ]);

                $count = $coachesWithoutStripe->count();

                // Log admin action
                DB::table('admin_actions')->insert([
                    'admin_id' => Auth::id(),
                    'action' => 'bulk_disable_coaches_without_stripe',
                    'target_type' => 'coach',
                    'details' => json_encode(['count' => $count, 'coach_ids' => $coachesWithoutStripe]),
                    'created_at' => now()
                ]);

                return response()->json([
                    'success' => true,
                    'message' => "{$count} coaches without Stripe disabled successfully"
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No action specified'
            ], 400);

        } catch (\Exception $e) {
            Log::error('Error bulk toggling coaches', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk toggle coaches',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send payment reminder email
     */
    public function sendPaymentReminder(Request $request)
    {
        try {
            // Verify admin
            if (!Auth::user()->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            $validated = $request->validate([
                'type' => 'required|string|in:organization,coach',
                'id' => 'required|integer',
                'reminder_type' => 'nullable|string|in:payment_overdue,stripe_connect'
            ]);

            $reminderType = $validated['reminder_type'] ?? 'payment_overdue';

            if ($validated['type'] === 'organization') {
                $organization = DB::table('organizations')->where('id', $validated['id'])->first();
                $leader = User::find($organization->leader_id);

                if ($leader) {
                    Mail::send('emails.payment_reminder_organization', [
                        'organization' => $organization,
                        'leader' => $leader
                    ], function ($message) use ($leader, $organization) {
                        $message->to($leader->email)
                            ->subject("Payment Reminder: {$organization->name}");
                    });
                }

            } else {
                $coach = User::find($validated['id']);

                if ($coach) {
                    Mail::send('emails.payment_reminder_coach', [
                        'coach' => $coach,
                        'reminder_type' => $reminderType
                    ], function ($message) use ($coach) {
                        $message->to($coach->email)
                            ->subject('Payment Setup Reminder - Connect Stripe');
                    });
                }
            }

            // Log admin action
            DB::table('admin_actions')->insert([
                'admin_id' => Auth::id(),
                'action' => 'send_payment_reminder',
                'target_type' => $validated['type'],
                'target_id' => $validated['id'],
                'details' => json_encode(['reminder_type' => $reminderType]),
                'created_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment reminder sent successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error sending payment reminder', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to send payment reminder',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store invoice document (ADMIN ONLY - others get email)
     */
    public function storeInvoiceDocument(Request $request)
    {
        try {
            // Verify admin
            if (!Auth::user()->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only admins can store invoice documents.'
                ], 403);
            }

            $validated = $request->validate([
                'invoice_id' => 'required|integer|exists:invoices,id',
                'document' => 'required|file|mimes:pdf|max:10240' // 10MB max
            ]);

            $invoice = Invoice::findOrFail($validated['invoice_id']);

            // Store document
            $path = $request->file('document')->store('admin_invoices', 'private');

            // Update invoice with document path
            $invoice->update([
                'admin_document_path' => $path,
                'admin_document_stored_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Invoice document stored successfully',
                'document_path' => $path
            ]);

        } catch (\Exception $e) {
            Log::error('Error storing invoice document', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to store invoice document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get admin invoice documents
     */
    public function getAdminInvoiceDocuments(Request $request)
    {
        try {
            // Verify admin
            if (!Auth::user()->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            $invoices = Invoice::whereNotNull('admin_document_path')
                ->orderBy('admin_document_stored_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'invoices' => $invoices
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching admin invoice documents', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch invoice documents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download admin invoice document
     */
    public function downloadAdminInvoiceDocument(Request $request, $invoiceId)
    {
        try {
            // Verify admin
            if (!Auth::user()->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            $invoice = Invoice::findOrFail($invoiceId);

            if (!$invoice->admin_document_path) {
                return response()->json([
                    'success' => false,
                    'message' => 'No document stored for this invoice'
                ], 404);
            }

            return Storage::disk('private')->download($invoice->admin_document_path, "invoice-{$invoice->invoice_number}.pdf");

        } catch (\Exception $e) {
            Log::error('Error downloading admin invoice document', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to download invoice document',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
