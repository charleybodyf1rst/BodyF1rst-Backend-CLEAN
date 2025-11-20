<?php

namespace App\Services;

class SafetyFilter
{
    private static array $crisisKeywords = [
        'suicide', 'kill myself', 'end my life', 'want to die', 'hurt myself',
        'self harm', 'cutting', 'overdose', 'not worth living', 'better off dead'
    ];

    public static function checkForCrisis(string $text): bool
    {
        $lowerText = strtolower($text);
        
        foreach (self::$crisisKeywords as $keyword) {
            if (str_contains($lowerText, $keyword)) {
                return true;
            }
        }
        
        return false;
    }

    public static function getCrisisResponse(): array
    {
        return [
            'route' => 'crisis',
            'message' => 'I\'m concerned about what you\'ve shared. Please reach out to a mental health professional or crisis hotline immediately. In the US, you can call 988 for the Suicide & Crisis Lifeline.',
            'resources' => [
                'US Crisis Lifeline' => '988',
                'Crisis Text Line' => 'Text HOME to 741741',
                'International' => 'https://findahelpline.com'
            ]
        ];
    }
}
