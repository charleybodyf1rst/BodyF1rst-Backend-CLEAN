<?php

namespace App\Actions;

use App\Services\MealLoggerService;
use App\Services\WorkoutPlannerService;
use App\Services\CalendarService;
use Illuminate\Support\Facades\Log;

class ToolRegistry
{
    private MealLoggerService $mealLogger;
    private WorkoutPlannerService $workoutPlanner;
    private CalendarService $calendar;

    public function __construct(
        MealLoggerService $mealLogger,
        WorkoutPlannerService $workoutPlanner,
        CalendarService $calendar
    ) {
        $this->mealLogger = $mealLogger;
        $this->workoutPlanner = $workoutPlanner;
        $this->calendar = $calendar;
    }

    /**
     * Get tool definitions for AI function calling.
     *
     * @return array
     */
    public static function definitions(): array
    {
        return [
            ['type' => 'function', 'function' => [
                'name' => 'log_meal',
                'description' => 'Log a meal with nutritional information',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'Name of the meal or food item'],
                        'calories' => ['type' => 'number', 'description' => 'Total calories'],
                        'protein_g' => ['type' => 'number', 'description' => 'Protein in grams'],
                        'carbs_g' => ['type' => 'number', 'description' => 'Carbohydrates in grams'],
                        'fat_g' => ['type' => 'number', 'description' => 'Fat in grams'],
                        'when' => ['type' => 'string', 'description' => 'ISO datetime when meal was consumed']
                    ],
                    'required' => ['name', 'calories']
                ]
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'plan_workout',
                'description' => 'Create a personalized workout plan',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'focus' => [
                            'type' => 'string',
                            'enum' => ['push', 'pull', 'legs', 'full', 'cardio'],
                            'description' => 'Workout focus area'
                        ],
                        'duration_min' => [
                            'type' => 'integer',
                            'description' => 'Desired workout duration in minutes',
                            'minimum' => 10,
                            'maximum' => 180
                        ]
                    ],
                    'required' => ['focus']
                ]
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'add_event',
                'description' => 'Add an event to the user\'s calendar',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string', 'description' => 'Event title'],
                        'start' => ['type' => 'string', 'description' => 'Start time in ISO 8601 format'],
                        'end' => ['type' => 'string', 'description' => 'End time in ISO 8601 format (optional)']
                    ],
                    'required' => ['title', 'start']
                ]
            ]],
        ];
    }

    /**
     * Execute a tool function call.
     *
     * @param string $name
     * @param array $args
     * @return array
     */
    public function call(string $name, array $args): array
    {
        try {
            // Validate and sanitize tool name
            $name = trim(strtolower($name));
            
            // Log tool call for monitoring
            Log::info('Tool function called', [
                'tool_name' => $name,
                'args' => $this->sanitizeArgsForLogging($args),
                'user_id' => auth()->id()
            ]);

            return match($name) {
                'log_meal' => $this->handleLogMeal($args),
                'plan_workout' => $this->handlePlanWorkout($args),
                'add_event' => $this->handleAddEvent($args),
                default => [
                    'ok' => false,
                    'error' => "Unknown tool: {$name}. Available tools: log_meal, plan_workout, add_event"
                ]
            };

        } catch (\Exception $e) {
            Log::error('Tool registry call failed', [
                'tool_name' => $name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'args' => $this->sanitizeArgsForLogging($args)
            ]);

            return [
                'ok' => false,
                'error' => 'Tool execution failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Handle meal logging tool call.
     *
     * @param array $args
     * @return array
     */
    private function handleLogMeal(array $args): array
    {
        try {
            return $this->mealLogger->logMeal($args);
        } catch (\Exception $e) {
            Log::error('Meal logging failed', [
                'error' => $e->getMessage(),
                'args' => $this->sanitizeArgsForLogging($args)
            ]);

            return [
                'ok' => false,
                'error' => 'Failed to log meal'
            ];
        }
    }

    /**
     * Handle workout planning tool call.
     *
     * @param array $args
     * @return array
     */
    private function handlePlanWorkout(array $args): array
    {
        try {
            return $this->workoutPlanner->planWorkout($args);
        } catch (\Exception $e) {
            Log::error('Workout planning failed', [
                'error' => $e->getMessage(),
                'args' => $this->sanitizeArgsForLogging($args)
            ]);

            return [
                'ok' => false,
                'error' => 'Failed to create workout plan'
            ];
        }
    }

    /**
     * Handle calendar event addition tool call.
     *
     * @param array $args
     * @return array
     */
    private function handleAddEvent(array $args): array
    {
        try {
            return $this->calendar->addEvent($args);
        } catch (\Exception $e) {
            Log::error('Calendar event creation failed', [
                'error' => $e->getMessage(),
                'args' => $this->sanitizeArgsForLogging($args)
            ]);

            return [
                'ok' => false,
                'error' => 'Failed to add calendar event'
            ];
        }
    }

    /**
     * Redact sensitive argument data before logging.
     */
    private function sanitizeArgsForLogging(array $args): array
    {
        $redactedKeys = ['password', 'pass', 'token', 'secret', 'key', 'api_key', 'access_token'];
        $sanitized = [];

        foreach ($args as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            $shouldRedact = false;

            foreach ($redactedKeys as $needle) {
                if (str_contains($normalizedKey, $needle)) {
                    $shouldRedact = true;
                    break;
                }
            }

            if ($shouldRedact) {
                $sanitized[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArgsForLogging($value);
                continue;
            }

            if (is_object($value)) {
                $sanitized[$key] = '[OBJECT]';
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    /**
     * Static factory method for backward compatibility.
     *
     * @param string $name
     * @param array $args
     * @return array
     */
    public static function callStatic(string $name, array $args): array
    {
        $registry = app()->make(self::class);
        return $registry->call($name, $args);
    }
}
