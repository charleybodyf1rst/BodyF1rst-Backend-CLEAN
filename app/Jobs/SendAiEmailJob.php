<?php

namespace App\Jobs;

use App\Models\CrmLead;
use App\Services\CrmEmailAgentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Send AI Email Job
 * Queued job for sending AI-powered emails to leads
 */
class SendAiEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60;
    public $backoff = [120, 600, 1800];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public CrmLead $lead,
        public ?string $subject = null,
        public ?string $template = null,
        public ?int $aiAgentId = null
    ) {
        $this->onQueue('ai-agents');
    }

    /**
     * Execute the job.
     */
    public function handle(CrmEmailAgentService $emailService): void
    {
        try {
            Log::info('Processing AI email job', [
                'lead_id' => $this->lead->id,
                'attempt' => $this->attempts(),
            ]);

            $communication = $emailService->sendAiEmail(
                $this->lead,
                $this->subject,
                $this->template,
                $this->aiAgentId
            );

            if ($communication) {
                Log::info('AI email sent successfully', [
                    'lead_id' => $this->lead->id,
                    'communication_id' => $communication->id,
                    'subject' => $this->subject,
                ]);
            } else {
                Log::warning('AI email failed to send', [
                    'lead_id' => $this->lead->id,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('AI email job exception', [
                'lead_id' => $this->lead->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('AI email job failed permanently', [
            'lead_id' => $this->lead->id,
            'subject' => $this->subject,
            'error' => $exception->getMessage(),
        ]);
    }
}
