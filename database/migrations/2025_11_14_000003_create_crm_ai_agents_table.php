<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM AI Agents Table Migration
 * Configures AI agents (SMS, Email, Voice) for automated lead engagement
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_ai_agents', function (Blueprint $table) {
            $table->id();

            // Agent Identity
            $table->string('name'); // "SMS Agent - Cold Outreach", "Email Agent - Follow-up"
            $table->string('type')->index(); // sms, email, voice
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();

            // AI Configuration
            $table->string('openai_model')->default('gpt-4'); // gpt-4, gpt-3.5-turbo
            $table->text('system_prompt'); // AI personality and instructions
            $table->text('conversation_prompt')->nullable(); // Prompt for ongoing conversations
            $table->integer('max_tokens')->default(500);
            $table->decimal('temperature', 2, 1)->default(0.7);
            $table->integer('max_retries')->default(3);

            // Voice Agent Specifics
            $table->string('voice_id')->nullable(); // ElevenLabs voice ID or Twilio voice
            $table->string('voice_language')->default('en-US');
            $table->decimal('voice_speed', 2, 1)->default(1.0);
            $table->string('voice_gender')->nullable(); // male, female
            $table->text('voice_greeting')->nullable(); // Opening script for calls

            // Email Agent Specifics
            $table->string('email_from_name')->nullable();
            $table->string('email_from_address')->nullable();
            $table->string('email_reply_to')->nullable();
            $table->text('email_signature')->nullable();
            $table->boolean('email_track_opens')->default(true);
            $table->boolean('email_track_clicks')->default(true);

            // SMS Agent Specifics
            $table->string('sms_from_number')->nullable(); // Twilio phone number

            // Behavior Rules
            $table->integer('max_conversations_per_day')->default(100);
            $table->integer('max_retries_per_lead')->default(3);
            $table->integer('retry_delay_hours')->default(24);
            $table->string('active_hours_start')->default('09:00'); // 24-hour format
            $table->string('active_hours_end')->default('17:00');
            $table->text('active_days')->default('["monday","tuesday","wednesday","thursday","friday"]'); // JSON array
            $table->string('timezone')->default('America/New_York');

            // Trigger Rules (when to engage)
            $table->boolean('trigger_on_new_lead')->default(true);
            $table->integer('trigger_delay_minutes')->default(5); // Wait 5 min before first contact
            $table->boolean('trigger_on_no_response')->default(true);
            $table->integer('no_response_threshold_hours')->default(72); // Re-engage after 72hrs
            $table->boolean('trigger_on_positive_sentiment')->default(false);
            $table->text('custom_triggers')->nullable(); // JSON array of custom trigger rules

            // Success Criteria
            $table->text('success_keywords')->nullable(); // JSON array: ["interested", "schedule", "demo"]
            $table->text('negative_keywords')->nullable(); // JSON array: ["not interested", "remove"]
            $table->string('handoff_condition')->nullable(); // When to hand off to human: qualified, positive_sentiment, requested

            // Performance Metrics
            $table->integer('total_conversations')->default(0);
            $table->integer('successful_engagements')->default(0);
            $table->integer('leads_qualified')->default(0);
            $table->integer('demos_booked')->default(0);
            $table->decimal('success_rate', 5, 2)->default(0.00); // Percentage
            $table->decimal('avg_response_time_seconds', 10, 2)->default(0.00);
            $table->decimal('avg_sentiment_score', 3, 2)->default(0.00);

            // Cost Tracking
            $table->decimal('total_cost', 10, 2)->default(0.00); // Total cost of API calls
            $table->decimal('cost_per_engagement', 10, 2)->default(0.00);
            $table->integer('total_tokens_used')->default(0);

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('type');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_ai_agents');
    }
};
