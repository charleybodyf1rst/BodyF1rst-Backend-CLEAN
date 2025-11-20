<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class CalendarRecurringPattern extends Model
{
    use HasFactory;

    protected $fillable = [
        'frequency',
        'interval',
        'days_of_week',
        'day_of_month',
        'month_of_year',
        'start_date',
        'end_date',
        'occurrence_count',
        'occurrences_created',
        'exception_dates',
        'timezone',
    ];

    protected $casts = [
        'days_of_week' => 'array',
        'exception_dates' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'interval' => 'integer',
        'occurrence_count' => 'integer',
        'occurrences_created' => 'integer',
    ];

    // Relationships

    public function events()
    {
        return $this->hasMany(CalendarEvent::class, 'recurring_pattern_id');
    }

    public function blockedTimes()
    {
        return $this->hasMany(CalendarBlockedTime::class, 'recurring_pattern_id');
    }

    // Methods

    public function getNextOccurrences($count = 10, $fromDate = null)
    {
        $fromDate = $fromDate ? Carbon::parse($fromDate) : now();
        $occurrences = [];
        $current = max($this->start_date, $fromDate);

        // Check if we've reached the limit
        if ($this->occurrence_count && $this->occurrences_created >= $this->occurrence_count) {
            return $occurrences;
        }

        // Check if we're past the end date
        if ($this->end_date && $current > $this->end_date) {
            return $occurrences;
        }

        $maxIterations = 1000; // Prevent infinite loops
        $iterations = 0;

        while (count($occurrences) < $count && $iterations < $maxIterations) {
            $iterations++;

            $nextDate = $this->calculateNextDate($current);

            if (!$nextDate) {
                break;
            }

            // Check if this date is an exception
            if ($this->exception_dates && in_array($nextDate->format('Y-m-d'), $this->exception_dates)) {
                $current = $nextDate->addDay();
                continue;
            }

            // Check if we're past the end date
            if ($this->end_date && $nextDate > $this->end_date) {
                break;
            }

            // Check if we've reached the occurrence limit
            if ($this->occurrence_count && $this->occurrences_created + count($occurrences) >= $this->occurrence_count) {
                break;
            }

            $occurrences[] = $nextDate;
            $current = $nextDate->copy()->addDay();
        }

        return $occurrences;
    }

    protected function calculateNextDate($fromDate)
    {
        $date = Carbon::parse($fromDate);

        switch ($this->frequency) {
            case 'daily':
                return $date->addDays($this->interval);

            case 'weekly':
            case 'biweekly':
                $weekInterval = $this->frequency === 'biweekly' ? 2 : 1;
                $weekInterval *= $this->interval;

                if ($this->days_of_week && count($this->days_of_week) > 0) {
                    // Find next occurrence on specified days
                    $currentDayOfWeek = $date->dayOfWeek;
                    $sortedDays = $this->days_of_week;
                    sort($sortedDays);

                    // Check if there's a day later in the current week
                    foreach ($sortedDays as $day) {
                        if ($day > $currentDayOfWeek) {
                            return $date->copy()->next($day);
                        }
                    }

                    // Move to next week(s) and use first day
                    return $date->copy()->addWeeks($weekInterval)->next($sortedDays[0]);
                } else {
                    return $date->addWeeks($weekInterval);
                }

            case 'monthly':
                if ($this->day_of_month) {
                    $date->addMonths($this->interval);
                    $date->day = min($this->day_of_month, $date->daysInMonth);
                    return $date;
                }
                return $date->addMonths($this->interval);

            case 'yearly':
                if ($this->month_of_year) {
                    $date->addYears($this->interval);
                    $date->month = $this->month_of_year;
                    if ($this->day_of_month) {
                        $date->day = min($this->day_of_month, $date->daysInMonth);
                    }
                    return $date;
                }
                return $date->addYears($this->interval);

            default:
                return null;
        }
    }

    public function shouldContinue()
    {
        // Check occurrence count limit
        if ($this->occurrence_count && $this->occurrences_created >= $this->occurrence_count) {
            return false;
        }

        // Check end date
        if ($this->end_date && now() > $this->end_date) {
            return false;
        }

        return true;
    }

    public function incrementOccurrencesCreated()
    {
        $this->increment('occurrences_created');
    }

    public function addException($date)
    {
        $exceptions = $this->exception_dates ?? [];
        $dateString = Carbon::parse($date)->format('Y-m-d');

        if (!in_array($dateString, $exceptions)) {
            $exceptions[] = $dateString;
            $this->exception_dates = $exceptions;
            $this->save();
        }
    }

    public function removeException($date)
    {
        $exceptions = $this->exception_dates ?? [];
        $dateString = Carbon::parse($date)->format('Y-m-d');

        $exceptions = array_filter($exceptions, function ($d) use ($dateString) {
            return $d !== $dateString;
        });

        $this->exception_dates = array_values($exceptions);
        $this->save();
    }
}
