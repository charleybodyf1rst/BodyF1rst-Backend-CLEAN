<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * HealthMetricHistory Model
 *
 * Stores detailed time-series data for individual health metrics
 * Multiple records per day for trend analysis and charting
 */
class HealthMetricHistory extends Model
{
    use HasFactory;

    protected $table = 'health_metric_history';

    public $timestamps = false; // Using custom timestamp fields

    protected $fillable = [
        'user_id',
        'metric_type',
        'metric_value',
        'metric_unit',
        'metadata',
        'source',
        'recorded_at',
        'synced_at',
    ];

    protected $casts = [
        'metric_value' => 'decimal:2',
        'metadata' => 'array',
        'recorded_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    /**
     * Get the user that owns the health metric history
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record a health metric reading
     */
    public static function record($userId, $metricType, $value, $unit = null, $metadata = null, $source = 'Manual', $recordedAt = null)
    {
        return self::create([
            'user_id' => $userId,
            'metric_type' => $metricType,
            'metric_value' => $value,
            'metric_unit' => $unit,
            'metadata' => $metadata,
            'source' => $source,
            'recorded_at' => $recordedAt ?? now(),
            'synced_at' => now(),
        ]);
    }

    /**
     * Get history for a specific metric type
     */
    public static function getMetricHistory($userId, $metricType, $startDate, $endDate)
    {
        return self::where('user_id', $userId)
            ->where('metric_type', $metricType)
            ->whereBetween('recorded_at', [$startDate, $endDate])
            ->orderBy('recorded_at', 'asc')
            ->get();
    }

    /**
     * Get latest reading for a specific metric
     */
    public static function getLatest($userId, $metricType)
    {
        return self::where('user_id', $userId)
            ->where('metric_type', $metricType)
            ->orderBy('recorded_at', 'desc')
            ->first();
    }

    /**
     * Get all metrics recorded in a time range
     */
    public static function getAllMetricsInRange($userId, $startDate, $endDate)
    {
        return self::where('user_id', $userId)
            ->whereBetween('recorded_at', [$startDate, $endDate])
            ->orderBy('recorded_at', 'desc')
            ->get()
            ->groupBy('metric_type');
    }

    /**
     * Calculate average for a metric over a period
     */
    public static function getAverage($userId, $metricType, $startDate, $endDate)
    {
        return self::where('user_id', $userId)
            ->where('metric_type', $metricType)
            ->whereBetween('recorded_at', [$startDate, $endDate])
            ->avg('metric_value');
    }
}
