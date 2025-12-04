<?php

namespace App\Services;

use App\Models\Exercise;
use Illuminate\Support\Collection;

/**
 * Exercise Matching Service
 * 
 * Matches exercise names from WOD syntax to exercises in the database.
 * Uses exact match priority and fuzzy matching for better results.
 */
class ExerciseMatchingService
{
    /**
     * Find the best matching exercise for a given name
     * 
     * @param string $exerciseName The exercise name from WOD syntax
     * @param int $userId The user ID to scope the search
     * @return Exercise|null The best matching exercise or null
     */
    public function findBestMatch(string $exerciseName, int $userId): ?Exercise
    {
        // Get all available exercises for the user
        $exercises = Exercise::availableToUser($userId)->get();
        
        if ($exercises->isEmpty()) {
            return null;
        }
        
        // Normalize the search term
        $normalizedSearch = $this->normalizeString($exerciseName);
        
        // Expand abbreviations in the search term
        // This transforms "KB Swings" -> "Kettlebell Swings"
        $expandedSearch = $this->expandAbbreviations($normalizedSearch);
        
        // Try exact match first (case-insensitive) with expanded search
        $exactMatch = $exercises->first(function ($exercise) use ($expandedSearch) {
            return $this->normalizeString($exercise->title) === $expandedSearch;
        });
        
        if ($exactMatch) {
            return $exactMatch;
        }
        
        // Also try exact match with original normalized search (in case no expansion happened)
        if ($expandedSearch !== $normalizedSearch) {
            $exactMatch = $exercises->first(function ($exercise) use ($normalizedSearch) {
                return $this->normalizeString($exercise->title) === $normalizedSearch;
            });
            
            if ($exactMatch) {
                return $exactMatch;
            }
        }
        
        // Try fuzzy matching with scoring (use expanded search)
        $scoredExercises = $exercises->map(function ($exercise) use ($expandedSearch, $exerciseName) {
            $normalizedTitle = $this->normalizeString($exercise->title);
            $score = $this->calculateMatchScore($expandedSearch, $normalizedTitle, $exerciseName, $exercise->title);
            
            return [
                'exercise' => $exercise,
                'score' => $score
            ];
        })
        ->filter(function ($item) use ($expandedSearch) {
            // Minimum score threshold to avoid false positives
            // Lower threshold for very short searches (abbreviations like "KB", "HSPU")
            // since they're likely intentional abbreviations
            $minScore = strlen($expandedSearch) <= 4 ? 100 : 150;
            return $item['score'] >= $minScore;
        })
        ->sortByDesc('score');
        
        if ($scoredExercises->isEmpty()) {
            return null;
        }
        
        // Return the highest scoring match
        return $scoredExercises->first()['exercise'];
    }
    
    /**
     * Normalize a string for comparison
     * 
     * @param string $str
     * @return string
     */
    private function normalizeString(string $str): string
    {
        // Convert to lowercase
        $str = strtolower($str);
        
        // Remove extra whitespace
        $str = preg_replace('/\s+/', ' ', $str);
        
        // Remove common punctuation but keep hyphens for compound words
        $str = preg_replace('/[^\w\s\-]/', '', $str);
        
        // Trim
        $str = trim($str);
        
        return $str;
    }
    
    /**
     * Expand abbreviations in a search term
     * Transforms "KB Swings" -> "Kettlebell Swings"
     * 
     * @param string $search Normalized search term
     * @return string Search term with abbreviations expanded
     */
    private function expandAbbreviations(string $search): string
    {
        $abbreviations = config('exercise_abbreviations', []);
        
        // Sort abbreviations by length (longest first) to avoid partial replacements
        // e.g., "kbs" should be checked before "kb"
        uksort($abbreviations, function($a, $b) {
            return strlen($b) - strlen($a);
        });
        
        foreach ($abbreviations as $abbr => $fullForms) {
            // Use word boundary to match whole words only
            // This prevents "kb" from matching inside "keyboard"
            $pattern = '/\b' . preg_quote($abbr, '/') . '\b/i';
            
            if (preg_match($pattern, $search)) {
                // Replace with the first (primary) full form
                $search = preg_replace($pattern, $fullForms[0], $search);
                break; // Only expand one abbreviation to avoid conflicts
            }
        }
        
        return $search;
    }
    
    /**
     * Calculate match score between search term and exercise title
     * 
     * Higher score = better match
     * 
     * @param string $normalizedSearch Normalized search term
     * @param string $normalizedTitle Normalized exercise title
     * @param string $originalSearch Original search term (for case-sensitive checks)
     * @param string $originalTitle Original exercise title (for case-sensitive checks)
     * @return int Match score
     */
    private function calculateMatchScore(
        string $normalizedSearch,
        string $normalizedTitle,
        string $originalSearch,
        string $originalTitle
    ): int {
        $score = 0;
        
        // Exact match (case-insensitive) - highest priority
        if ($normalizedSearch === $normalizedTitle) {
            $score += 1000;
        }
        
        // Exact match (case-sensitive) - bonus points
        if ($originalSearch === $originalTitle) {
            $score += 100;
        }
        
        // Title starts with search term
        if (str_starts_with($normalizedTitle, $normalizedSearch)) {
            $score += 500;
        }
        
        // Title ends with search term
        if (str_ends_with($normalizedTitle, $normalizedSearch)) {
            $score += 300;
        }
        
        // Title contains search term as whole word
        if (preg_match('/\b' . preg_quote($normalizedSearch, '/') . '\b/', $normalizedTitle)) {
            $score += 400;
        }
        
        // Title contains search term anywhere
        if (str_contains($normalizedTitle, $normalizedSearch)) {
            $score += 200;
        }
        
        // Fuzzy matching: handle common variations
        $searchVariations = $this->generateVariations($normalizedSearch);
        foreach ($searchVariations as $variation) {
            if ($normalizedTitle === $variation) {
                $score += 800;
                break;
            }
            if (str_contains($normalizedTitle, $variation)) {
                $score += 150;
                break;
            }
        }
        
        // Length similarity bonus (prefer similar length matches)
        $lengthDiff = abs(strlen($normalizedSearch) - strlen($normalizedTitle));
        if ($lengthDiff <= 3) {
            $score += 50;
        }
        
        // Penalize very long titles when search is short (avoid false positives)
        if (strlen($normalizedSearch) < 10 && strlen($normalizedTitle) > strlen($normalizedSearch) * 3) {
            $score -= 100;
        }
        
        return $score;
    }
    
    /**
     * Generate common variations of an exercise name
     * 
     * @param string $name Normalized exercise name
     * @return array Array of variations
     */
    private function generateVariations(string $name): array
    {
        $variations = [];
        
        // Handle hyphen variations
        // "pull-ups" <-> "pullups" <-> "pull ups"
        if (str_contains($name, '-')) {
            $variations[] = str_replace('-', '', $name);
            $variations[] = str_replace('-', ' ', $name);
        } elseif (str_contains($name, ' ')) {
            $variations[] = str_replace(' ', '-', $name);
            $variations[] = str_replace(' ', '', $name);
        } else {
            // Try adding hyphens/spaces in common positions
            // "pullups" -> "pull-ups", "pull ups"
            $commonSplits = ['up', 'down', 'over', 'under', 'out', 'in'];
            foreach ($commonSplits as $split) {
                if (str_contains($name, $split)) {
                    $pos = strpos($name, $split);
                    if ($pos > 0) {
                        $before = substr($name, 0, $pos);
                        $after = substr($name, $pos);
                        $variations[] = $before . '-' . $after;
                        $variations[] = $before . ' ' . $after;
                    }
                }
            }
        }
        
        // Handle plural variations
        // "squat" <-> "squats"
        if (str_ends_with($name, 's')) {
            $variations[] = substr($name, 0, -1);
        } else {
            $variations[] = $name . 's';
        }
        
        // Load abbreviations from config
        $abbreviations = config('exercise_abbreviations', []);
        
        foreach ($abbreviations as $abbr => $fullForms) {
            // Check if the search term is the abbreviation
            if ($name === $abbr) {
                // Add all full forms as variations
                foreach ($fullForms as $full) {
                    $variations[] = $full;
                }
            }
            
            // Check if the search term contains the abbreviation
            if (str_contains($name, $abbr)) {
                foreach ($fullForms as $full) {
                    $variations[] = str_replace($abbr, $full, $name);
                }
            }
            
            // Check if the search term is or contains any of the full forms
            foreach ($fullForms as $full) {
                if ($name === $full) {
                    $variations[] = $abbr;
                }
                if (str_contains($name, $full)) {
                    $variations[] = str_replace($full, $abbr, $name);
                }
            }
        }
        
        return array_unique($variations);
    }
}
