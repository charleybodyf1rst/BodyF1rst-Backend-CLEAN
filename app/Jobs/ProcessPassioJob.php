<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use App\Models\PassioEntity;
use App\Models\NutritionLog;
use Illuminate\Support\Carbon;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class ProcessPassioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public $timeout = 120;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $retryAfter = 60;

    protected $foodId;
    protected $userId;
    protected $quantity;
    protected $mealType;

    /**
     * Create a new job instance.
     */
    public function __construct($foodId, $userId, $quantity = 1, $mealType = null)
    {
        $this->foodId = $this->sanitizeFoodId($foodId);
        $this->userId = $userId;
        $this->quantity = $quantity;
        $this->mealType = $mealType;
        $this->onQueue('bodyf1rst-passio-jobs');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Check if we have cached nutrition data
            $passioEntity = PassioEntity::where('external_id', $this->foodId)->first();
            
            if (!$passioEntity || $this->shouldRefreshCache($passioEntity)) {
                // Fetch fresh data from Passio API
                $nutritionData = $this->fetchFromPassioAPI($this->foodId);
                
                if ($nutritionData) {
                    // Update or create cached entity
                    $passioEntity = PassioEntity::updateOrCreate(
                        ['external_id' => $this->foodId],
                        [
                            'label' => $nutritionData['label'] ?? 'Unknown Food',
                            'nutrition' => $nutritionData,
                            'metadata' => $nutritionData['metadata'] ?? [],
                            'hash' => md5(json_encode($nutritionData)),
                            'last_updated' => now()
                        ]
                    );
                }
            }
            
            if ($passioEntity) {
                // Calculate nutrition for the specified quantity
                $nutrition = $this->calculateNutrition($passioEntity->nutrition, $this->quantity);
                
                // Create nutrition log entry
                NutritionLog::create([
                    'user_id' => $this->userId,
                    'logged_at' => now(),
                    'calories' => $nutrition['calories'] ?? 0,
                    'macros' => $nutrition,
                    'source' => 'passio',
                    'food_name' => $passioEntity->label,
                    'quantity' => $this->quantity,
                    'unit' => 'serving',
                    'meal_type' => $this->mealType,
                    'passio_id' => $this->foodId,
                    'metadata' => [
                        'passio_entity_id' => $passioEntity->id,
                        'processed_at' => now()->toISOString()
                    ]
                ]);
            }
            
        } catch (\Exception $e) {
            \Log::error('Passio job processing failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function sanitizeFoodId($foodId): string
    {
        if (is_int($foodId) || (is_string($foodId) && ctype_digit($foodId))) {
            $sanitized = (string) $foodId;
        } else {
            $sanitized = trim((string) $foodId);
        }

        if ($sanitized === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $sanitized)) {
            throw new InvalidArgumentException('Invalid Passio food identifier provided.');
        }

        return $sanitized;
    }

    /**
     * Fetch nutrition data from Passio API
     */
    private function fetchFromPassioAPI($foodId)
    {
        $apiKey = config('services.passio.api_key');
        
        if (!$apiKey) {
            \Log::warning('Passio API key not configured');
            return null;
        }

        try {
            $response = Http::timeout(30)
                ->retry(3, 1000)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json'
                ])
                ->get('https://api.passiolife.com/v2/foods/' . urlencode($foodId));

            if ($response->successful()) {
                $data = $response->json();
                
                // Validate response structure
                if (!is_array($data) || empty($data)) {
                    \Log::warning('Passio API returned invalid data structure', [
                        'food_id' => $foodId,
                        'response_body' => $response->body()
                    ]);
                    return null;
                }
                
                return $data;
            }

            // Log non-successful responses
            \Log::error('Passio API request failed', [
                'food_id' => $foodId,
                'status_code' => $response->status(),
                'response_body' => $response->body(),
                'headers' => $response->headers()
            ]);
            
            return null;
            
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            \Log::error('Passio API connection failed', [
                'food_id' => $foodId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts()
            ]);
            
            // Re-throw to trigger job retry
            throw $e;
            
        } catch (\Illuminate\Http\Client\RequestException $e) {
            \Log::error('Passio API request exception', [
                'food_id' => $foodId,
                'error' => $e->getMessage(),
                'response' => $e->response ? $e->response->body() : null
            ]);
            
            return null;
            
        } catch (\Exception $e) {
            \Log::error('Unexpected error in Passio API call', [
                'food_id' => $foodId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return null;
        }
    }

    /**
     * Check if cached data should be refreshed
     */
    private function shouldRefreshCache($passioEntity)
    {
        $lastUpdated = $passioEntity->last_updated ?? null;
        $threshold = now()->subDays(7);

        if ($lastUpdated instanceof CarbonInterface) {
            return $lastUpdated->lt($threshold);
        }

        if (is_string($lastUpdated) && $lastUpdated !== '') {
            try {
                $parsed = Carbon::parse($lastUpdated);
                return $parsed->lt($threshold);
            } catch (\Exception $exception) {
                \Log::warning('Failed to parse Passio entity last_updated value', [
                    'passio_entity_id' => $passioEntity->id ?? null,
                    'last_updated' => $lastUpdated,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        // Missing or invalid timestamp should trigger refresh
        return true;
    }

    /**
     * Calculate nutrition values for specified quantity
     */
    private function calculateNutrition($baseNutrition, $quantity)
    {
        $calculated = [];
        
        foreach ($baseNutrition as $key => $value) {
            if (is_numeric($value)) {
                $calculated[$key] = $value * $quantity;
            } else {
                $calculated[$key] = $value;
            }
        }
        
        return $calculated;
    }
}
