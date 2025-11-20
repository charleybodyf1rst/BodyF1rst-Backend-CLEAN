<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPerformanceIndexes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Users table indexes - Critical for authentication and lookups
        Schema::table('users', function (Blueprint $table) {
            if (!$this->indexExists('users', 'users_email_index')) {
                $table->index('email');
            }
            if (!$this->indexExists('users', 'users_role_index')) {
                $table->index('role');
            }
            if (!$this->indexExists('users', 'users_is_active_index')) {
                $table->index('is_active');
            }
            if (!$this->indexExists('users', 'users_created_at_index')) {
                $table->index('created_at');
            }
            if (!$this->indexExists('users', 'users_last_activity_at_index')) {
                $table->index('last_activity_at');
            }
        });

        // Coach client relationships - Frequent joins
        Schema::table('coach_client_relationships', function (Blueprint $table) {
            if (!$this->indexExists('coach_client_relationships', 'coach_client_relationships_status_index')) {
                $table->index('status');
            }
            if (!$this->indexExists('coach_client_relationships', 'coach_client_relationships_assigned_at_index')) {
                $table->index('assigned_at');
            }
        });

        // Nutrition logs - Large table with many queries
        Schema::table('nutrition_logs', function (Blueprint $table) {
            if (!$this->indexExists('nutrition_logs', 'nutrition_logs_logged_at_index')) {
                $table->index('logged_at');
            }
            if (!$this->indexExists('nutrition_logs', 'nutrition_logs_user_logged_composite')) {
                $table->index(['user_id', 'logged_at']);
            }
        });

        // Workout logs - Performance critical
        Schema::table('workout_logs', function (Blueprint $table) {
            if (!$this->indexExists('workout_logs', 'workout_logs_logged_at_index')) {
                $table->index('logged_at');
            }
            if (!$this->indexExists('workout_logs', 'workout_logs_completion_status_index')) {
                $table->index('completion_status');
            }
            if (!$this->indexExists('workout_logs', 'workout_logs_user_logged_composite')) {
                $table->index(['user_id', 'logged_at']);
            }
        });

        // Calendar events - Date range queries
        Schema::table('calendar_events', function (Blueprint $table) {
            if (!$this->indexExists('calendar_events', 'calendar_events_start_time_index')) {
                $table->index('start_time');
            }
            if (!$this->indexExists('calendar_events', 'calendar_events_user_start_composite')) {
                $table->index(['user_id', 'start_time']);
            }
        });

        // App notifications - Large volume
        Schema::table('app_notifications', function (Blueprint $table) {
            if (!$this->indexExists('app_notifications', 'app_notifications_created_at_index')) {
                $table->index('created_at');
            }
            if (!$this->indexExists('app_notifications', 'app_notifications_scheduled_for_index')) {
                $table->index('scheduled_for');
            }
        });

        // Payments - Financial queries
        Schema::table('payments', function (Blueprint $table) {
            if (!$this->indexExists('payments', 'payments_payment_date_index')) {
                $table->index('payment_date');
            }
            if (!$this->indexExists('payments', 'payments_transaction_id_index')) {
                $table->index('transaction_id');
            }
        });

        // Invoices - Billing queries
        Schema::table('invoices', function (Blueprint $table) {
            if (!$this->indexExists('invoices', 'invoices_invoice_date_index')) {
                $table->index('invoice_date');
            }
            if (!$this->indexExists('invoices', 'invoices_paid_at_index')) {
                $table->index('paid_at');
            }
        });

        // Coach session payments - Revenue tracking
        if (Schema::hasTable('coach_session_payments')) {
            Schema::table('coach_session_payments', function (Blueprint $table) {
                if (!$this->indexExists('coach_session_payments', 'coach_session_payments_created_at_index')) {
                    $table->index('created_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Drop indexes in reverse order
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->dropIndex(['role']);
            $table->dropIndex(['is_active']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['last_activity_at']);
        });

        Schema::table('coach_client_relationships', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['assigned_at']);
        });

        Schema::table('nutrition_logs', function (Blueprint $table) {
            $table->dropIndex(['logged_at']);
            $table->dropIndex(['user_id', 'logged_at']);
        });

        Schema::table('workout_logs', function (Blueprint $table) {
            $table->dropIndex(['logged_at']);
            $table->dropIndex(['completion_status']);
            $table->dropIndex(['user_id', 'logged_at']);
        });

        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropIndex(['start_time']);
            $table->dropIndex(['user_id', 'start_time']);
        });

        Schema::table('app_notifications', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['scheduled_for']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['payment_date']);
            $table->dropIndex(['transaction_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['invoice_date']);
            $table->dropIndex(['paid_at']);
        });

        if (Schema::hasTable('coach_session_payments')) {
            Schema::table('coach_session_payments', function (Blueprint $table) {
                $table->dropIndex(['created_at']);
            });
        }
    }

    /**
     * Check if an index exists on a table.
     */
    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $doctrineSchemaManager = $connection->getDoctrineSchemaManager();
        $doctrineTable = $doctrineSchemaManager->listTableDetails($table);

        return $doctrineTable->hasIndex($index);
    }
}
