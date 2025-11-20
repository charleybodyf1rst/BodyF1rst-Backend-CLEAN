<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class TokenUsageMonitorService
{
    private const CACHE_KEY_PREFIX = 'token_usage_';
    private const DAILY_LIMIT = 100000; // 100k tokens per day
    private const WEEKLY_LIMIT = 500000; // 500k tokens per week
    private const MONTHLY_LIMIT = 1500000; // 1.5M tokens per month
    
    private const WARNING_THRESHOLD = 0.8; // 80% of limit
    private const CRITICAL_THRESHOLD = 0.95; // 95% of limit

    public function recordTokenUsage(int $tokensUsed, string $model = 'unknown', string $userId = null): void
    {
        $timestamp = now();
        $date = $timestamp->format('Y-m-d');
        $week = $timestamp->format('Y-W');
        $month = $timestamp->format('Y-m');

        // Record usage by time period
        $this->incrementUsage('daily', $date, $tokensUsed);
        $this->incrementUsage('weekly', $week, $tokensUsed);
        $this->incrementUsage('monthly', $month, $tokensUsed);

        // Record usage by model
        $this->incrementUsage('model_daily', "{$model}_{$date}", $tokensUsed);
        
        // Record usage by user if provided
        if ($userId) {
            $this->incrementUsage('user_daily', "{$userId}_{$date}", $tokensUsed);
            $this->incrementUsage('user_monthly', "{$userId}_{$month}", $tokensUsed);
        }

        // Log detailed usage
        Log::info('Token usage recorded', [
            'tokens_used' => $tokensUsed,
            'model' => $model,
            'user_id' => $userId,
            'timestamp' => $timestamp->toISOString(),
            'daily_total' => $this->getDailyUsage($date),
            'weekly_total' => $this->getWeeklyUsage($week),
            'monthly_total' => $this->getMonthlyUsage($month)
        ]);

        // Check for threshold alerts
        $this->checkThresholds($date, $week, $month);
    }

    public function getDailyUsage(string $date = null): int
    {
        $date = $date ?? now()->format('Y-m-d');
        return Cache::get($this->getCacheKey('daily', $date), 0);
    }

    public function getWeeklyUsage(string $week = null): int
    {
        $week = $week ?? now()->format('Y-W');
        return Cache::get($this->getCacheKey('weekly', $week), 0);
    }

    public function getMonthlyUsage(string $month = null): int
    {
        $month = $month ?? now()->format('Y-m');
        return Cache::get($this->getCacheKey('monthly', $month), 0);
    }

    public function getUserDailyUsage(string $userId, string $date = null): int
    {
        $date = $date ?? now()->format('Y-m-d');
        return Cache::get($this->getCacheKey('user_daily', "{$userId}_{$date}"), 0);
    }

    public function getUserMonthlyUsage(string $userId, string $month = null): int
    {
        $month = $month ?? now()->format('Y-m');
        return Cache::get($this->getCacheKey('user_monthly', "{$userId}_{$month}"), 0);
    }

    public function getModelUsage(string $model, string $date = null): int
    {
        $date = $date ?? now()->format('Y-m-d');
        return Cache::get($this->getCacheKey('model_daily', "{$model}_{$date}"), 0);
    }

    public function getUsageStats(): array
    {
        $today = now()->format('Y-m-d');
        $thisWeek = now()->format('Y-W');
        $thisMonth = now()->format('Y-m');

        $dailyUsage = $this->getDailyUsage($today);
        $weeklyUsage = $this->getWeeklyUsage($thisWeek);
        $monthlyUsage = $this->getMonthlyUsage($thisMonth);

        return [
            'daily' => [
                'usage' => $dailyUsage,
                'limit' => self::DAILY_LIMIT,
                'percentage' => round(($dailyUsage / self::DAILY_LIMIT) * 100, 2),
                'remaining' => max(0, self::DAILY_LIMIT - $dailyUsage)
            ],
            'weekly' => [
                'usage' => $weeklyUsage,
                'limit' => self::WEEKLY_LIMIT,
                'percentage' => round(($weeklyUsage / self::WEEKLY_LIMIT) * 100, 2),
                'remaining' => max(0, self::WEEKLY_LIMIT - $weeklyUsage)
            ],
            'monthly' => [
                'usage' => $monthlyUsage,
                'limit' => self::MONTHLY_LIMIT,
                'percentage' => round(($monthlyUsage / self::MONTHLY_LIMIT) * 100, 2),
                'remaining' => max(0, self::MONTHLY_LIMIT - $monthlyUsage)
            ]
        ];
    }

    public function getDetailedStats(int $days = 7): array
    {
        $stats = [];
        
        for ($i = 0; $i < $days; $i++) {
            $date = now()->subDays($i)->format('Y-m-d');
            $usage = $this->getDailyUsage($date);
            
            $stats[] = [
                'date' => $date,
                'usage' => $usage,
                'percentage' => round(($usage / self::DAILY_LIMIT) * 100, 2)
            ];
        }

        return array_reverse($stats);
    }

    public function getTopModels(string $date = null, int $limit = 10): array
    {
        $date = $date ?? now()->format('Y-m-d');
        $models = [];

        // This is a simplified version - in production you'd want to store model usage more efficiently
        $commonModels = ['gpt-4', 'gpt-3.5-turbo', 'vision-model', 'claude-3', 'gemini-pro'];
        
        foreach ($commonModels as $model) {
            $usage = $this->getModelUsage($model, $date);
            if ($usage > 0) {
                $models[] = [
                    'model' => $model,
                    'usage' => $usage,
                    'percentage' => round(($usage / $this->getDailyUsage($date)) * 100, 2)
                ];
            }
        }

        // Sort by usage descending
        usort($models, fn($a, $b) => $b['usage'] - $a['usage']);

        return array_slice($models, 0, $limit);
    }

    public function isUsageLimitExceeded(string $period = 'daily'): bool
    {
        switch ($period) {
            case 'daily':
                return $this->getDailyUsage() >= self::DAILY_LIMIT;
            case 'weekly':
                return $this->getWeeklyUsage() >= self::WEEKLY_LIMIT;
            case 'monthly':
                return $this->getMonthlyUsage() >= self::MONTHLY_LIMIT;
            default:
                return false;
        }
    }

    public function getRemainingTokens(string $period = 'daily'): int
    {
        switch ($period) {
            case 'daily':
                return max(0, self::DAILY_LIMIT - $this->getDailyUsage());
            case 'weekly':
                return max(0, self::WEEKLY_LIMIT - $this->getWeeklyUsage());
            case 'monthly':
                return max(0, self::MONTHLY_LIMIT - $this->getMonthlyUsage());
            default:
                return 0;
        }
    }

    private function incrementUsage(string $type, string $key, int $tokens): void
    {
        $cacheKey = $this->getCacheKey($type, $key);
        $currentUsage = Cache::get($cacheKey, 0);
        $newUsage = $currentUsage + $tokens;
        
        // Set cache with appropriate TTL
        $ttl = $this->getCacheTTL($type);
        Cache::put($cacheKey, $newUsage, $ttl);
    }

    private function getCacheKey(string $type, string $key): string
    {
        return self::CACHE_KEY_PREFIX . $type . '_' . $key;
    }

    private function getCacheTTL(string $type): int
    {
        switch ($type) {
            case 'daily':
            case 'user_daily':
            case 'model_daily':
                return 86400; // 24 hours
            case 'weekly':
                return 604800; // 7 days
            case 'monthly':
            case 'user_monthly':
                return 2592000; // 30 days
            default:
                return 3600; // 1 hour default
        }
    }

    private function checkThresholds(string $date, string $week, string $month): void
    {
        $dailyUsage = $this->getDailyUsage($date);
        $weeklyUsage = $this->getWeeklyUsage($week);
        $monthlyUsage = $this->getMonthlyUsage($month);

        // Check daily thresholds
        $this->checkThreshold('daily', $dailyUsage, self::DAILY_LIMIT, $date);
        
        // Check weekly thresholds
        $this->checkThreshold('weekly', $weeklyUsage, self::WEEKLY_LIMIT, $week);
        
        // Check monthly thresholds
        $this->checkThreshold('monthly', $monthlyUsage, self::MONTHLY_LIMIT, $month);
    }

    private function checkThreshold(string $period, int $usage, int $limit, string $timeKey): void
    {
        $percentage = $usage / $limit;
        $alertKey = "token_alert_{$period}_{$timeKey}";

        if ($percentage >= self::CRITICAL_THRESHOLD && !Cache::has($alertKey . '_critical')) {
            $this->sendAlert('critical', $period, $usage, $limit, $percentage);
            Cache::put($alertKey . '_critical', true, 3600); // Don't spam alerts
        } elseif ($percentage >= self::WARNING_THRESHOLD && !Cache::has($alertKey . '_warning')) {
            $this->sendAlert('warning', $period, $usage, $limit, $percentage);
            Cache::put($alertKey . '_warning', true, 3600);
        }
    }

    private function sendAlert(string $level, string $period, int $usage, int $limit, float $percentage): void
    {
        $message = sprintf(
            'Token usage %s alert: %s usage is at %.1f%% (%s/%s tokens)',
            $level,
            $period,
            $percentage * 100,
            number_format($usage),
            number_format($limit)
        );

        // Log the alert
        Log::warning('Token usage alert', [
            'level' => $level,
            'period' => $period,
            'usage' => $usage,
            'limit' => $limit,
            'percentage' => $percentage * 100,
            'remaining' => $limit - $usage
        ]);

        // Send notification (implement based on your notification preferences)
        $this->sendNotification($level, $message, [
            'period' => $period,
            'usage' => $usage,
            'limit' => $limit,
            'percentage' => $percentage * 100,
            'remaining' => $limit - $usage
        ]);
    }

    private function sendNotification(string $level, string $message, array $data): void
    {
        // Implement your notification logic here
        // This could be email, Slack, Discord, SMS, etc.
        
        // Example: Log to a specific channel
        Log::channel('alerts')->warning($message, $data);
        
        // Example: Send email to admin
        if ($level === 'critical' && config('mail.admin_email')) {
            try {
                // You would implement an actual mail class here
                // Mail::to(config('mail.admin_email'))->send(new TokenUsageAlert($data));
            } catch (\Exception $e) {
                Log::error('Failed to send token usage alert email', ['error' => $e->getMessage()]);
            }
        }
    }

    public function resetUsage(string $period, string $key = null): bool
    {
        try {
            $key = $key ?? match($period) {
                'daily' => now()->format('Y-m-d'),
                'weekly' => now()->format('Y-W'),
                'monthly' => now()->format('Y-m'),
                default => throw new \InvalidArgumentException("Invalid period: {$period}")
            };

            $cacheKey = $this->getCacheKey($period, $key);
            Cache::forget($cacheKey);
            
            Log::info('Token usage reset', [
                'period' => $period,
                'key' => $key,
                'cache_key' => $cacheKey
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to reset token usage', [
                'period' => $period,
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
