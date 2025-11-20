<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\TokenUsageMonitorService;
use Carbon\Carbon;

class TokenUsageController extends Controller
{
    private TokenUsageMonitorService $tokenMonitor;

    public function __construct(TokenUsageMonitorService $tokenMonitor)
    {
        $this->tokenMonitor = $tokenMonitor;
    }

    /**
     * Get current token usage statistics
     */
    public function getUsageStats(): JsonResponse
    {
        try {
            $stats = $this->tokenMonitor->getUsageStats();
            
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve usage statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detailed usage history
     */
    public function getDetailedStats(Request $request): JsonResponse
    {
        try {
            $days = $request->input('days', 7);
            $days = min(max($days, 1), 30); // Limit between 1-30 days
            
            $stats = $this->tokenMonitor->getDetailedStats($days);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'period_days' => $days,
                    'history' => $stats
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve detailed statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get usage by model
     */
    public function getModelUsage(Request $request): JsonResponse
    {
        try {
            $date = $request->input('date');
            $limit = $request->input('limit', 10);
            
            $modelStats = $this->tokenMonitor->getTopModels($date, $limit);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'date' => $date ?? now()->format('Y-m-d'),
                    'models' => $modelStats
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve model usage statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user-specific usage
     */
    public function getUserUsage(Request $request, string $userId): JsonResponse
    {
        try {
            $date = $request->input('date');
            $month = $request->input('month');
            
            $dailyUsage = $this->tokenMonitor->getUserDailyUsage($userId, $date);
            $monthlyUsage = $this->tokenMonitor->getUserMonthlyUsage($userId, $month);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'user_id' => $userId,
                    'daily_usage' => $dailyUsage,
                    'monthly_usage' => $monthlyUsage,
                    'date' => $date ?? now()->format('Y-m-d'),
                    'month' => $month ?? now()->format('Y-m')
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user usage statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if usage limits are exceeded
     */
    public function checkLimits(): JsonResponse
    {
        try {
            $limits = [
                'daily' => [
                    'exceeded' => $this->tokenMonitor->isUsageLimitExceeded('daily'),
                    'remaining' => $this->tokenMonitor->getRemainingTokens('daily')
                ],
                'weekly' => [
                    'exceeded' => $this->tokenMonitor->isUsageLimitExceeded('weekly'),
                    'remaining' => $this->tokenMonitor->getRemainingTokens('weekly')
                ],
                'monthly' => [
                    'exceeded' => $this->tokenMonitor->isUsageLimitExceeded('monthly'),
                    'remaining' => $this->tokenMonitor->getRemainingTokens('monthly')
                ]
            ];
            
            return response()->json([
                'success' => true,
                'data' => $limits
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check usage limits',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Record token usage (for manual tracking)
     */
    public function recordUsage(Request $request): JsonResponse
    {
        $request->validate([
            'tokens_used' => 'required|integer|min:1|max:100000',
            'model' => 'nullable|string|max:50',
            'user_id' => 'nullable|string|max:50'
        ]);

        try {
            $this->tokenMonitor->recordTokenUsage(
                $request->input('tokens_used'),
                $request->input('model', 'manual'),
                $request->input('user_id')
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Token usage recorded successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record token usage',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset usage for a specific period (admin only)
     */
    public function resetUsage(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'required|in:daily,weekly,monthly',
            'key' => 'nullable|string|max:20'
        ]);

        try {
            $success = $this->tokenMonitor->resetUsage(
                $request->input('period'),
                $request->input('key')
            );
            
            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Usage reset successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to reset usage'
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset usage',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get usage dashboard data
     */
    public function getDashboard(): JsonResponse
    {
        try {
            $stats = $this->tokenMonitor->getUsageStats();
            $recentHistory = $this->tokenMonitor->getDetailedStats(7);
            $topModels = $this->tokenMonitor->getTopModels();
            $limits = [
                'daily' => $this->tokenMonitor->isUsageLimitExceeded('daily'),
                'weekly' => $this->tokenMonitor->isUsageLimitExceeded('weekly'),
                'monthly' => $this->tokenMonitor->isUsageLimitExceeded('monthly')
            ];
            
            return response()->json([
                'success' => true,
                'data' => [
                    'current_stats' => $stats,
                    'recent_history' => $recentHistory,
                    'top_models' => $topModels,
                    'limits_exceeded' => $limits,
                    'generated_at' => now()->toISOString()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate dashboard data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
