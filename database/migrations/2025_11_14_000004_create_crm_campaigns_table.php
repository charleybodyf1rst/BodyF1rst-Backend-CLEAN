<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM Campaigns Table Migration
 * Email drip campaigns, SMS blasts, cold calling campaigns
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_campaigns', function (Blueprint $table) {
            $table->id();

            // Campaign Details
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type')->index(); // email_drip, sms_blast, cold_calling, nurture
            $table->string('status')->default('draft')->index(); // draft, active, paused, completed
            $table->unsignedBigInteger('ai_agent_id')->nullable()->index(); // AI agent executing this

            // Targeting
            $table->text('target_segment')->nullable(); // JSON: filters for leads
            $table->text('include_lead_ids')->nullable(); // JSON: specific lead IDs
            $table->text('exclude_lead_ids')->nullable(); // JSON: leads to exclude
            $table->string('target_lead_status')->nullable(); // new, contacted, etc.
            $table->integer('target_lead_score_min')->nullable();
            $table->integer('target_lead_score_max')->nullable();

            // Schedule
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->string('schedule_type')->default('immediate'); // immediate, scheduled, recurring
            $table->text('schedule_config')->nullable(); // JSON: cron-like config for recurring

            // Email Campaign Specifics
            $table->string('email_subject')->nullable();
            $table->text('email_body')->nullable(); // Plain text
            $table->text('email_body_html')->nullable(); // HTML version
            $table->boolean('email_use_ai_personalization')->default(false);
            $table->text('email_attachments')->nullable(); // JSON array

            // SMS Campaign Specifics
            $table->text('sms_message')->nullable();
            $table->boolean('sms_use_ai_personalization')->default(false);

            // Voice Campaign Specifics
            $table->text('voice_script')->nullable();
            $table->string('voice_agent_id')->nullable(); // Which voice agent

            // A/B Testing
            $table->boolean('ab_testing_enabled')->default(false);
            $table->string('ab_variant')->nullable(); // A, B, C
            $table->text('ab_test_config')->nullable(); // JSON with variants

            // Performance Metrics
            $table->integer('total_recipients')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('delivered_count')->default(0);
            $table->integer('opened_count')->default(0);
            $table->integer('clicked_count')->default(0);
            $table->integer('replied_count')->default(0);
            $table->integer('converted_count')->default(0);
            $table->integer('unsubscribed_count')->default(0);
            $table->integer('bounced_count')->default(0);
            $table->integer('failed_count')->default(0);

            // Calculated Rates
            $table->decimal('delivery_rate', 5, 2)->default(0.00); // Percentage
            $table->decimal('open_rate', 5, 2)->default(0.00);
            $table->decimal('click_rate', 5, 2)->default(0.00);
            $table->decimal('reply_rate', 5, 2)->default(0.00);
            $table->decimal('conversion_rate', 5, 2)->default(0.00);

            // Cost
            $table->decimal('estimated_cost', 10, 2)->nullable();
            $table->decimal('actual_cost', 10, 2)->default(0.00);
            $table->decimal('cost_per_conversion', 10, 2)->nullable();

            // Attribution
            $table->unsignedBigInteger('created_by_user_id')->nullable();

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys
            $table->foreign('ai_agent_id')->references('id')->on('crm_ai_agents')->onDelete('set null');
            $table->foreign('created_by_user_id')->references('id')->on('admins')->onDelete('set null');

            // Indexes
            $table->index(['status', 'type']);
            $table->index('start_date');
        });

        // Campaign Recipients (many-to-many)
        Schema::create('crm_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id')->index();
            $table->unsignedBigInteger('lead_id')->index();
            $table->string('status')->default('pending'); // pending, sent, delivered, opened, clicked, replied, failed
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('campaign_id')->references('id')->on('crm_campaigns')->onDelete('cascade');
            $table->foreign('lead_id')->references('id')->on('crm_leads')->onDelete('cascade');

            $table->unique(['campaign_id', 'lead_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_campaign_recipients');
        Schema::dropIfExists('crm_campaigns');
    }
};
