<?php

return [

    /*
    |--------------------------------------------------------------------------
    | BodyF1rst Application Configuration
    |--------------------------------------------------------------------------
    |
    | Centralized configuration for BodyF1rst-specific values.
    | This prevents hardcoding values throughout the application.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Pagination & Limits
    |--------------------------------------------------------------------------
    */
    'pagination' => [
        'default_per_page' => env('DEFAULT_PER_PAGE', 20),
        'max_per_page' => env('MAX_PER_PAGE', 100),
        'recent_activities_limit' => 10,
        'top_clients_limit' => 5,
        'upcoming_sessions_limit' => 5,
        'recent_workouts_limit' => 10,
        'popular_workouts_limit' => 5,
        'recent_logs_limit' => 10,
        'popular_meal_plans_limit' => 5,
        'cbt_activities_limit' => 10,
        'notifications_limit' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Workout Configuration
    |--------------------------------------------------------------------------
    */
    'workout' => [
        'compliance_threshold' => 70, // Percentage for "on track" status
        'streak_days_lookback' => 30, // Days to check for workout streak
        'progress_weeks_lookback' => 12, // Weeks for progress charts
        'default_rest_time' => 60, // Seconds
        'default_sets' => 3,
        'default_reps' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Nutrition Configuration
    |--------------------------------------------------------------------------
    */
    'nutrition' => [
        'default_daily_calories' => 2000,
        'default_protein_percentage' => 30,
        'default_carbs_percentage' => 40,
        'default_fat_percentage' => 30,
        'meal_log_days_lookback' => 30,
        'compliance_threshold' => 90, // Percentage
    ],

    /*
    |--------------------------------------------------------------------------
    | CBT (Cognitive Behavioral Therapy) Configuration
    |--------------------------------------------------------------------------
    */
    'cbt' => [
        'default_duration_weeks' => 8,
        'mood_scale_min' => 1,
        'mood_scale_max' => 10,
        'session_types' => ['individual', 'group', 'self-guided'],
        'exercise_types' => [
            'thought_record',
            'behavioral_activation',
            'exposure',
            'relaxation',
            'cognitive_restructuring'
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Coach Dashboard Configuration
    |--------------------------------------------------------------------------
    */
    'coach' => [
        'stats_days_lookback' => 30,
        'retention_months' => 3, // Months for retention rate calculation
        'revenue_tracking_enabled' => true,
        'auto_notification_enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Weekly Check-in Configuration
    |--------------------------------------------------------------------------
    */
    'checkin' => [
        'energy_scale_min' => 1,
        'energy_scale_max' => 10,
        'mood_scale_min' => 1,
        'mood_scale_max' => 10,
        'sleep_quality_min' => 1,
        'sleep_quality_max' => 10,
        'stress_level_min' => 1,
        'stress_level_max' => 10,
        'max_sleep_hours' => 24,
        'photo_max_size_kb' => 10240, // 10MB
    ],

    /*
    |--------------------------------------------------------------------------
    | File Upload Configuration
    |--------------------------------------------------------------------------
    */
    'uploads' => [
        'max_image_size_kb' => 10240, // 10MB
        'max_video_size_kb' => 102400, // 100MB
        'max_document_size_kb' => 10240, // 10MB
        'allowed_image_types' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'allowed_video_types' => ['mp4', 'mov', 'avi', 'webm'],
        'allowed_document_types' => ['pdf', 'doc', 'docx', 'xls', 'xlsx'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Configuration
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        'channels' => ['push', 'email', 'sms'],
        'default_channels' => ['push', 'email'],
        'priority_levels' => ['low', 'normal', 'high', 'urgent'],
        'default_priority' => 'normal',
        'schedule_types' => ['immediate', 'scheduled'],
        'retention_days' => 90, // Days to keep old notifications
    ],

    /*
    |--------------------------------------------------------------------------
    | Security & Rate Limiting
    |--------------------------------------------------------------------------
    */
    'security' => [
        'failed_login_attempts_max' => 5,
        'failed_login_lockout_minutes' => 30,
        'password_min_length' => 8,
        'token_expiration_hours' => 24,
        'admin_token_expiration_hours' => 12,
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics & Reporting
    |--------------------------------------------------------------------------
    */
    'analytics' => [
        'date_range_periods' => ['week', 'month', 'quarter', 'year'],
        'default_period' => 'month',
        'trend_chart_days' => 30,
        'performance_benchmark_days' => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | Measurement Units
    |--------------------------------------------------------------------------
    */
    'units' => [
        'weight' => ['lbs', 'kg'],
        'height' => ['in', 'cm'],
        'distance' => ['mi', 'km'],
        'volume' => ['oz', 'ml'],
        'default_weight' => 'lbs',
        'default_height' => 'in',
        'default_distance' => 'mi',
        'default_volume' => 'oz',
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment & Billing Configuration
    |--------------------------------------------------------------------------
    */
    'billing' => [
        'currency' => 'USD',
        'payment_overdue_grace_days' => 7,
        'invoice_retention_years' => 7,
        'auto_disable_on_payment_failure' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    */
    'features' => [
        'ai_nutrition_advisor' => env('FEATURE_AI_NUTRITION', true),
        'cbt_therapy' => env('FEATURE_CBT', true),
        'social_community' => env('FEATURE_SOCIAL', true),
        'wearables_integration' => env('FEATURE_WEARABLES', true),
        'video_library' => env('FEATURE_VIDEO_LIBRARY', true),
        'meal_planning' => env('FEATURE_MEAL_PLANNING', true),
        'progress_photos' => env('FEATURE_PROGRESS_PHOTOS', true),
        'coach_marketplace' => env('FEATURE_COACH_MARKETPLACE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | External API Timeouts
    |--------------------------------------------------------------------------
    */
    'api_timeouts' => [
        'default' => 30, // seconds
        'stripe' => 30,
        'openai' => 60,
        'passio' => 30,
        'fatsecret' => 30,
        'fcm' => 30,
        'onesignal' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache TTL (Time To Live) in Seconds
    |--------------------------------------------------------------------------
    */
    'cache_ttl' => [
        'user_stats' => 3600, // 1 hour
        'workout_plans' => 7200, // 2 hours
        'nutrition_data' => 1800, // 30 minutes
        'coach_dashboard' => 600, // 10 minutes
        'public_content' => 86400, // 24 hours
        'api_responses' => 300, // 5 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    */
    'queues' => [
        'default' => 'default',
        'notifications' => 'notifications',
        'emails' => 'emails',
        'analytics' => 'analytics',
        'ai_processing' => 'ai',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'log_failed_jobs' => true,
        'log_slow_queries' => true,
        'slow_query_threshold_ms' => 1000, // 1 second
        'log_api_requests' => env('LOG_API_REQUESTS', false),
        'log_retention_days' => 30,
    ],
];
