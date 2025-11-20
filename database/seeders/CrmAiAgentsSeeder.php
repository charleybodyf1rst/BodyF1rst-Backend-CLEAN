<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * CRM AI Agents Seeder
 * Seeds the default AI agents (SMS, Email, Voice)
 */
class CrmAiAgentsSeeder extends Seeder
{
    public function run(): void
    {
        $agents = [
            [
                'name' => 'SMS Agent - Cold Outreach',
                'type' => 'sms',
                'description' => 'Friendly SMS agent for initial lead outreach',
                'is_active' => true,
                'model' => 'gpt-4',
                'system_prompt' => 'You are Sarah, a friendly and professional wellness consultant from Body First. Your goal is to engage new leads via SMS with warmth and curiosity. Keep messages under 160 characters. Ask open-ended questions about their wellness goals. Be conversational, not salesy. If they show interest, qualify their needs (company size, timeline, budget). If they say "STOP", apologize and confirm unsubscribe.',
                'max_tokens' => 100,
                'temperature' => 0.7,
                'sms_phone_number' => '+1234567890', // Will be replaced with actual Twilio number
                'max_conversations_per_day' => 100,
                'active_hours_start' => '09:00',
                'active_hours_end' => '17:00',
                'timezone' => 'America/New_York',
                'trigger_on_new_lead' => true,
                'trigger_delay_minutes' => 5,
                'handoff_keywords' => json_encode(['speak to human', 'call me', 'manager', 'supervisor']),
                'handoff_to_human' => true,
                'success_keywords' => json_encode(['yes', 'interested', 'tell me more', 'definitely', 'sounds good']),
                'total_conversations' => 0,
                'successful_conversations' => 0,
                'failed_conversations' => 0,
                'avg_sentiment_score' => 0,
                'total_cost' => 0,
                'total_tokens_used' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Email Agent - Drip Campaign',
                'type' => 'email',
                'description' => 'AI-powered email nurture sequences',
                'is_active' => true,
                'model' => 'gpt-4',
                'system_prompt' => 'You are a professional email copywriter for Body First, a corporate wellness company. Write engaging, personalized emails that educate and nurture leads. Focus on benefits, use storytelling, include clear CTAs. Keep emails concise (200-300 words). Personalize with {contact_first_name} and {company_name}. Maintain professional yet warm tone.',
                'max_tokens' => 500,
                'temperature' => 0.8,
                'email_from_address' => 'sarah@bodyf1rst.com',
                'email_signature' => "Best regards,\nSarah Connor\nWellness Consultant\nBody First\nPhone: (555) 123-4567\nwww.bodyf1rst.com",
                'email_tracking_enabled' => true,
                'max_conversations_per_day' => 200,
                'active_hours_start' => '08:00',
                'active_hours_end' => '18:00',
                'timezone' => 'America/New_York',
                'trigger_on_new_lead' => false, // Triggered by campaigns
                'trigger_delay_minutes' => 60,
                'total_conversations' => 0,
                'successful_conversations' => 0,
                'failed_conversations' => 0,
                'avg_sentiment_score' => 0,
                'total_cost' => 0,
                'total_tokens_used' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Voice Agent - Qualification Calls',
                'type' => 'voice',
                'description' => 'AI voice agent for qualification calls',
                'is_active' => false, // Start inactive until configured
                'model' => 'gpt-4',
                'system_prompt' => 'You are Sarah, a friendly voice assistant from Body First. Your goal is to qualify leads by asking about their wellness program needs, company size, timeline, and budget. Be conversational and empathetic. If the lead is qualified, schedule a demo with the sales team. If they have questions you cannot answer, offer to transfer to a human representative.',
                'max_tokens' => 150,
                'temperature' => 0.7,
                'voice_id' => 'EXAVITQu4vr4xnSDxMaL', // ElevenLabs Sarah voice
                'voice_language' => 'en-US',
                'voice_speed' => 1.0,
                'voice_greeting' => 'Hi! This is Sarah from Body First. I wanted to reach out about your interest in our corporate wellness programs. Is now a good time to chat for just a couple minutes?',
                'max_conversations_per_day' => 50,
                'active_hours_start' => '10:00',
                'active_hours_end' => '16:00',
                'timezone' => 'America/New_York',
                'trigger_on_new_lead' => false, // Triggered manually
                'handoff_keywords' => json_encode(['human', 'representative', 'person', 'agent']),
                'handoff_to_human' => true,
                'total_conversations' => 0,
                'successful_conversations' => 0,
                'failed_conversations' => 0,
                'avg_sentiment_score' => 0,
                'total_cost' => 0,
                'total_tokens_used' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('crm_ai_agents')->insert($agents);

        $this->command->info('✅ AI agents seeded successfully!');
    }
}
