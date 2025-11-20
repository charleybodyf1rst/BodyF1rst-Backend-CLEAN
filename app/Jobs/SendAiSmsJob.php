<?php

namespace App\Jobs;

use App\Models\CrmLead;
use App\Services\CrmSmsAgentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Send AI SMS Job
 * Queued job for sending AI-powered SMS to leads
 */
class SendAiSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 30;
    public $backoff = [60, 300, 900]; // Retry after 1min, 5min, 15min

    /**
     * Create a new job instance.
     */
    public function __construct(
        public CrmLead $lead,
        public ?string $customMessage = null
    ) {
        $this->onQueue('ai-agents');
    }

    /**
     * Execute the job.
     */
    public function handle(CrmSmsAgentService $smsService): void
    {
        try {
            Log::info('Processing AI SMS job', [
                'lead_id' => $this->lead->id,
                'attempt' => $this->attempts(),
            ]);

            $communication = $smsService->sendAiSms($this->lead, $this->customMessage);

            if ($communication) {
                Log::info('AI SMS sent successfully', [
                    'lead_id' => $this->lead->id,
                    'communication_id' => $communication->id,
                ]);
            } else {
                Log::warning('AI SMS failed to send', [
                    'lead_id' => $this->lead->id,
                ]);

                // Retry if we haven't exceeded max attempts
                if ($this->attempts() < $this->tries) {
                    $this->release($this->backoff[$this->attempts() - 1] ?? 900);
                }
            }

        } catch (\Exception $e) {
            Log::error('AI SMS job exception', [
                'lead_id' => $this->lead->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Re-throw to trigger retry logic
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('AI SMS job failed permanently', [
            'lead_id' => $this->lead->id,
            'error' => $exception->getMessage(),
        ]);

        // TODO: Notify sales team that AI SMS failed
        // Could send Slack notification or email alert
    }
}
