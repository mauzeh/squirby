<?php

namespace App\Services;

/**
 * WOD Parser Service
 * 
 * Parses WOD (Workout of the Day) text syntax into structured data.
 * 
 * Syntax Examples:
 * 
 * # Block 1: Strength
 * [Back Squat] 5-5-5-5-5
 * [Bench Press] 3x8
 * 
 * # Block 2: Conditioning
 * AMRAP 12min:
 * 10 [Box Jumps]
 * 15 [Push-ups]
 * 
 * Brackets [...] = exercises that can be logged
 * Colon after exercise name is optional
 */
class WodParser
{
    /**
     * Parse WOD text into structured data
     * 
     * @param string $text
     * @return array
     */
    public function parse(string $text): array
    {
        $lines = explode("\n", $text);
        $blocks = [];
        $currentBlock = null;
        $currentSpecialFormat = null;
        
        foreach ($lines as $lineNumber => $line) {
            $line = rtrim($line);
            
            // Skip empty lines and comments
            if ($this->isEmptyOrComment($line)) {
                continue;
            }
            
            // Check for block header
            if ($this->isBlockHeader($line)) {
                // Save any remaining special format to current block
                if ($currentSpecialFormat !== null && $currentBlock !== null) {
                    $currentBlock['exercises'][] = $currentSpecialFormat;
                    $currentSpecialFormat = null;
                }
                
                // Save previous block if exists
                if ($currentBlock !== null) {
                    $blocks[] = $currentBlock;
                }
                
                // Start new block
                $currentBlock = [
                    'name' => $this->parseBlockName($line),
                    'exercises' => []
                ];
                continue;
            }
            
            // If no block started yet, create a default one with empty name
            if ($currentBlock === null) {
                $currentBlock = [
                    'name' => '',
                    'exercises' => []
                ];
            }
            
            // Check for special format (lines starting with >)
            if ($this->isSpecialFormat($line)) {
                // Save previous special format if exists
                if ($currentSpecialFormat !== null) {
                    $currentBlock['exercises'][] = $currentSpecialFormat;
                }
                $currentSpecialFormat = $this->parseSpecialFormat($line);
                continue;
            }
            
            // Try to parse as exercise
            $exercise = $this->parseExercise($line);
            
            if ($exercise) {
                // If we're in a special format, add to it
                if ($currentSpecialFormat !== null) {
                    $currentSpecialFormat['exercises'][] = $exercise;
                } else {
                    // Otherwise add as regular exercise to block
                    $currentBlock['exercises'][] = $exercise;
                }
            }
        }
        
        // Save any remaining special format
        if ($currentSpecialFormat !== null) {
            $currentBlock['exercises'][] = $currentSpecialFormat;
        }
        
        // Save last block
        if ($currentBlock !== null) {
            $blocks[] = $currentBlock;
        }
        
        return [
            'blocks' => $blocks,
            'parsed_at' => now()->toIso8601String()
        ];
    }
    
    /**
     * Check if line is empty or a comment
     */
    private function isEmptyOrComment(string $line): bool
    {
        $trimmed = trim($line);
        return empty($trimmed) || 
               str_starts_with($trimmed, '//') || 
               str_starts_with($trimmed, '--');
    }
    
    /**
     * Check if line is a block header
     */
    private function isBlockHeader(string $line): bool
    {
        return preg_match('/^#{1,3}\s*/', $line);
    }
    
    /**
     * Parse block name from header
     */
    private function parseBlockName(string $line): string
    {
        return trim(preg_replace('/^#{1,3}\s*/', '', $line));
    }
    
    /**
     * Check if line is a special format (starts with >)
     */
    private function isSpecialFormat(string $line): bool
    {
        $trimmed = trim($line);
        return str_starts_with($trimmed, '>');
    }
    
    /**
     * Parse special format line
     */
    private function parseSpecialFormat(string $line): array
    {
        $trimmed = trim($line);
        
        // Remove the leading > and any whitespace after it
        $description = trim(substr($trimmed, 1));
        
        return [
            'type' => 'special_format',
            'description' => $description,
            'exercises' => []
        ];
    }
    
    /**
     * Parse regular exercise line
     */
    private function parseExercise(string $line): ?array
    {
        $trimmed = trim($line);
        
        // Strip markdown list markers (bullets and numbered lists) but preserve WOD syntax
        // Markdown list formats:
        //   - Unordered: "* ", "- ", "+ " (with optional leading whitespace)
        //   - Ordered: "1. ", "123. " (number followed by period and space)
        // After stripping, we preserve everything else (reps, exercise names, schemes)
        
        // First strip leading whitespace
        $trimmed = ltrim($trimmed);
        
        // Strip ordered list markers: "1. ", "123. ", etc.
        $trimmed = preg_replace('/^\d+\.\s+/', '', $trimmed);
        
        // Strip unordered list markers: "* ", "- ", "+ "
        $trimmed = preg_replace('/^[\*\-\+]\s+/', '', $trimmed);
        
        // Format: "10 [Exercise Name]" (for AMRAP/EMOM/etc)
        if (preg_match('/^(\d+)\s+\[([^\]]+)\]$/', $trimmed, $matches)) {
            return [
                'type' => 'exercise',
                'name' => trim($matches[2]),
                'reps' => (int)$matches[1]
            ];
        }
        
        // Format: "[Exercise Name]" (just the exercise, no scheme)
        if (preg_match('/^\[([^\]]+)\]$/', $trimmed, $matches)) {
            return [
                'type' => 'exercise',
                'name' => trim($matches[1])
            ];
        }
        
        // Format with colon: "[Exercise Name]: 3x8"
        if (preg_match('/^\[([^\]]+)\]:\s*(.+)$/', $trimmed, $matches)) {
            $name = trim($matches[1]);
            $scheme = trim($matches[2]);
            
            if (empty($name) || empty($scheme)) {
                return null;
            }
            
            return [
                'type' => 'exercise',
                'name' => $name,
                'scheme' => $scheme
            ];
        }
        
        // Format without colon but with text after: "[Exercise Name] any text here"
        if (preg_match('/^\[([^\]]+)\]\s+(.+)$/', $trimmed, $matches)) {
            return [
                'type' => 'exercise',
                'name' => trim($matches[1]),
                'scheme' => trim($matches[2])
            ];
        }
        
        return null;
    }

    
    /**
     * Convert parsed data back to text format
     */
    public function unparse(array $parsed): string
    {
        $lines = [];
        
        foreach ($parsed['blocks'] as $block) {
            $lines[] = '# ' . $block['name'];
            
            foreach ($block['exercises'] as $exercise) {
                if ($exercise['type'] === 'special_format') {
                    $lines[] = $this->unparseSpecialFormat($exercise);
                    foreach ($exercise['exercises'] as $subExercise) {
                        $lines[] = $this->unparseExercise($subExercise);
                    }
                } else {
                    $lines[] = $this->unparseExercise($exercise);
                }
            }
            
            $lines[] = ''; // Blank line between blocks
        }
        
        return implode("\n", $lines);
    }
    
    private function unparseSpecialFormat(array $format): string
    {
        return '> ' . ($format['description'] ?? '');
    }
    
    private function unparseExercise(array $exercise): string
    {
        $name = $exercise['name'];
        $brackets = '[' . $name . ']';
        
        // If exercise has reps (from AMRAP/EMOM/etc), format as "10 [Exercise]"
        if (isset($exercise['reps'])) {
            return $exercise['reps'] . ' ' . $brackets;
        }
        
        // If exercise has scheme, format as "[Exercise]: 3x8"
        if (isset($exercise['scheme'])) {
            return $brackets . ': ' . $exercise['scheme'];
        }
        
        // Just the exercise name
        return $brackets;
    }

    /**
     * Extract all exercise names from WOD syntax
     * 
     * This is a simpler method that just extracts exercise names from [Exercise Name] patterns
     * without doing full parsing. Useful for generating exercise lists.
     * 
     * @param string $text WOD syntax text
     * @return array Array of unique exercise names
     */
    public function extractLoggableExercises(string $text): array
    {
        $exerciseNames = [];
        
        // Find all [Exercise Name] patterns
        if (preg_match_all('/\[([^\]]+)\]/', $text, $matches)) {
            $exerciseNames = array_unique(array_map('trim', $matches[1]));
        }
        
        return array_values($exerciseNames); // Re-index array
    }
}
