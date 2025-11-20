<?php

namespace App\Services;

use App\Models\CrmLead;
use App\Models\CrmCommunication;
use App\Models\CrmLeadActivity;
use App\Models\CrmAiAgent;
use App\Models\CrmCampaign;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * CRM Email Agent Service
 * Handles AI-powered email campaigns and drip sequences
 */
class CrmEmailAgentService
{
    protected $sesRegion;
    protected $fromAddress;
    protected $fromName;
    protected $openaiApiKey;

    public function __construct()
    {
        $this->sesRegion = config('services.aws.ses.region');
        $this->fromAddress = config('services.aws.ses.from_address');
        $this->fromName = config('services.aws.ses.from_name');
        $this->openaiApiKey = config('services.openai.api_key');
    }

    /**
     * Send AI-generated email to lead
     */
    public function sendAiEmail(
        CrmLead $lead,
        ?string $subject = null,
        ?string $template = null,
        ?int $aiAgentId = null
    ): ?CrmCommunication {
        try {
            // Get active email AI agent
            $aiAgent = $aiAgentId
                ? CrmAiAgent::find($aiAgentId)
                : CrmAiAgent::where('type', 'email')->where('is_active', true)->first();

            if (!$aiAgent) {
                Log::error('No active email AI agent found');
                return null;
            }

            // Generate email content using AI
            $emailContent = $this->generateAiEmailContent($lead, $aiAgent, $template);

            // Use provided subject or generate one
            $emailSubject = $subject ?? $this->generateAiSubject($lead, $aiAgent);

            // Send via AWS SES
            $messageId = $this->sendViaSES(
                $lead->contact_email,
                $emailSubject,
                $emailContent['html'],
                $emailContent['text']
            );

            if ($messageId) {
                // Log communication
                $communication = CrmCommunication::create([
                    'lead_id' => $lead->id,
                    'type' => 'email',
                    'direction' => 'outbound',
                    'channel' => 'ai_agent',
                    'ai_agent_type' => 'email_agent',
                    'ai_agent_id' => $aiAgent->id,
                    'subject' => $emailSubject,
                    'content' => $emailContent['text'],
                    'content_html' => $emailContent['html'],
                    'email_from' => $this->fromAddress,
                    'email_to' => $lead->contact_email,
                    'email_message_id' => $messageId,
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);

                // Update lead engagement
                $lead->update([
                    'last_email_sent_at' => now(),
                    'ai_engaged' => true,
                    'last_ai_interaction_at' => now(),
                ]);

                // Log activity
                CrmLeadActivity::create([
                    'lead_id' => $lead->id,
                    'activity_type' => 'email_sent',
                    'description' => "AI email agent sent: {$emailSubject}",
                    'communication_id' => $communication->id,
                    'ai_agent_id' => $aiAgent->id,
                    'activity_at' => now(),
                ]);

                Log::info('AI email sent successfully', [
                    'lead_id' => $lead->id,
                    'message_id' => $messageId,
                ]);

                return $communication;
            }

            return null;

        } catch (\Exception $e) {
            Log::error('AI email service error', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Send drip campaign email
     */
    public function sendCampaignEmail(
        CrmLead $lead,
        CrmCampaign $campaign,
        int $sequenceNumber = 1
    ): ?CrmCommunication {
        try {
            $aiAgent = CrmAiAgent::find($campaign->ai_agent_id);

            // Personalize email content
            $subject = $this->personalizeContent($campaign->email_subject, $lead);
            $htmlBody = $this->personalizeContent($campaign->email_body_html, $lead);
            $textBody = strip_tags($htmlBody);

            // Send via SES
            $messageId = $this->sendViaSES(
                $lead->contact_email,
                $subject,
                $htmlBody,
                $textBody
            );

            if ($messageId) {
                $communication = CrmCommunication::create([
                    'lead_id' => $lead->id,
                    'type' => 'email',
                    'direction' => 'outbound',
                    'channel' => 'automated',
                    'campaign_id' => $campaign->id,
                    'ai_agent_id' => $aiAgent?->id,
                    'subject' => $subject,
                    'content' => $textBody,
                    'content_html' => $htmlBody,
                    'email_from' => $this->fromAddress,
                    'email_to' => $lead->contact_email,
                    'email_message_id' => $messageId,
                    'status' => 'sent',
                    'is_automated' => true,
                    'automation_trigger' => 'campaign_sequence',
                    'sent_at' => now(),
                ]);

                // Update campaign metrics
                $campaign->increment('emails_sent');

                // Log activity
                CrmLeadActivity::create([
                    'lead_id' => $lead->id,
                    'activity_type' => 'email_sent',
                    'description' => "Campaign email sent: {$campaign->name} (Sequence {$sequenceNumber})",
                    'communication_id' => $communication->id,
                    'campaign_id' => $campaign->id,
                    'activity_at' => now(),
                ]);

                return $communication;
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Campaign email failed', [
                'lead_id' => $lead->id,
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Generate AI email content using OpenAI
     */
    protected function generateAiEmailContent(CrmLead $lead, CrmAiAgent $aiAgent, ?string $template = null): array
    {
        try {
            $prompt = $template ?? "Generate a personalized email for {$lead->contact_first_name} from {$lead->company_name}. They're a {$lead->contact_title} interested in corporate wellness programs. Company size: {$lead->company_size} employees. Make it friendly, professional, and include a clear call-to-action.";

            $response = Http::withToken($this->openaiApiKey)
                ->timeout(30)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $aiAgent->model ?? 'gpt-4',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $aiAgent->system_prompt,
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'max_tokens' => $aiAgent->max_tokens ?? 500,
                    'temperature' => $aiAgent->temperature ?? 0.7,
                ]);

            if ($response->successful()) {
                $content = $response->json()['choices'][0]['message']['content'];

                // Personalize content
                $content = $this->personalizeContent($content, $lead);

                // Convert to HTML
                $html = $this->convertToHtml($content, $aiAgent);

                return [
                    'text' => $content,
                    'html' => $html,
                ];
            } else {
                // Fallback template
                return $this->getFallbackTemplate($lead, $aiAgent);
            }

        } catch (\Exception $e) {
            Log::error('OpenAI email generation failed', ['error' => $e->getMessage()]);
            return $this->getFallbackTemplate($lead, $aiAgent);
        }
    }

    /**
     * Generate AI email subject
     */
    protected function generateAiSubject(CrmLead $lead, CrmAiAgent $aiAgent): string
    {
        try {
            $response = Http::withToken($this->openaiApiKey)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $aiAgent->model ?? 'gpt-4',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => "Generate a compelling email subject line (max 50 chars) for a corporate wellness email to {$lead->contact_first_name} at {$lead->company_name}. Make it personalized and intriguing.",
                        ],
                    ],
                    'max_tokens' => 30,
                    'temperature' => 0.8,
                ]);

            if ($response->successful()) {
                $subject = trim($response->json()['choices'][0]['message']['content'], '"\' ');
                return substr($subject, 0, 100);
            }

        } catch (\Exception $e) {
            Log::error('AI subject generation failed', ['error' => $e->getMessage()]);
        }

        // Fallback subject
        return "{$lead->contact_first_name}, Transform Your Team's Wellness";
    }

    /**
     * Personalize content with lead data
     */
    protected function personalizeContent(string $content, CrmLead $lead): string
    {
        $replacements = [
            '{contact_first_name}' => $lead->contact_first_name,
            '{contact_last_name}' => $lead->contact_last_name,
            '{contact_full_name}' => $lead->full_contact_name,
            '{contact_title}' => $lead->contact_title ?? 'there',
            '{company_name}' => $lead->company_name,
            '{company_size}' => $lead->company_size ?? 'your',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    /**
     * Convert plain text to HTML email
     */
    protected function convertToHtml(string $text, CrmAiAgent $aiAgent): string
    {
        $paragraphs = explode("\n\n", $text);
        $html = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';

        foreach ($paragraphs as $paragraph) {
            $html .= '<p>' . nl2br(htmlspecialchars($paragraph)) . '</p>';
        }

        // Add signature
        if ($aiAgent->email_signature) {
            $html .= '<br><div style="border-top: 1px solid #ddd; padding-top: 20px; margin-top: 30px;">';
            $html .= nl2br(htmlspecialchars($aiAgent->email_signature));
            $html .= '</div>';
        }

        $html .= '</body></html>';

        return $html;
    }

    /**
     * Send email via AWS SES
     */
    protected function sendViaSES(string $to, string $subject, string $htmlBody, string $textBody): ?string
    {
        try {
            // TODO: Implement actual AWS SES sending
            // For now, log the email
            Log::info('Email would be sent via SES', [
                'to' => $to,
                'subject' => $subject,
            ]);

            // Return fake message ID for testing
            return 'msg_' . uniqid();

            // Production implementation:
            // $ses = new \Aws\Ses\SesClient([...]);
            // $result = $ses->sendEmail([...]);
            // return $result->get('MessageId');

        } catch (\Exception $e) {
            Log::error('SES send failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get fallback email template
     */
    protected function getFallbackTemplate(CrmLead $lead, CrmAiAgent $aiAgent): array
    {
        $text = "Hi {$lead->contact_first_name},\n\n";
        $text .= "Thank you for your interest in Body First's corporate wellness programs!\n\n";
        $text .= "I'd love to learn more about {$lead->company_name}'s wellness goals and how we can help your team thrive.\n\n";
        $text .= "Would you be available for a quick 15-minute call this week?\n\n";
        $text .= "Best regards,\nSarah Connor\nBody First";

        $html = $this->convertToHtml($text, $aiAgent);

        return ['text' => $text, 'html' => $html];
    }

    /**
     * Track email open (via pixel/webhook)
     */
    public function trackEmailOpen(string $messageId): void
    {
        $communication = CrmCommunication::where('email_message_id', $messageId)->first();

        if ($communication && !$communication->opened_at) {
            $communication->update([
                'opened_at' => now(),
                'open_count' => 1,
            ]);

            $communication->lead->increment('email_opens');
        } elseif ($communication) {
            $communication->increment('open_count');
            $communication->touch('opened_at');
        }
    }

    /**
     * Track email click
     */
    public function trackEmailClick(string $messageId, string $url): void
    {
        $communication = CrmCommunication::where('email_message_id', $messageId)->first();

        if ($communication) {
            if (!$communication->clicked_at) {
                $communication->update(['clicked_at' => now()]);
            }

            $communication->increment('click_count');
            $communication->lead->increment('email_clicks');

            // Log activity
            CrmLeadActivity::create([
                'lead_id' => $communication->lead_id,
                'activity_type' => 'email_clicked',
                'description' => "Clicked link in email: {$url}",
                'communication_id' => $communication->id,
                'activity_at' => now(),
            ]);
        }
    }
}
