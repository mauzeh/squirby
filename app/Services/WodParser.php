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
 * [[Back Squat]] 5-5-5-5-5
 * [[Bench Press]] 3x8
 * 
 * # Block 2: Conditioning
 * AMRAP 12min:
 * 10 [[Box Jumps]]
 * 15 [[Push-ups]]
 * 
 * Double brackets [[...]] = loggable exercises
 * Single brackets [...] = non-loggable exercises (warm-ups, stretches, etc.)
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
        
        // Format: "10 [[Exercise Name]]" (loggable, for AMRAP/EMOM/etc)
        if (preg_match('/^(\d+)\s+\[\[([^\]]+)\]\]$/', $trimmed, $matches)) {
            return [
                'type' => 'exercise',
                'name' => trim($matches[2]),
                'reps' => (int)$matches[1],
                'loggable' => true
            ];
        }
        
        // Format: "10 [Exercise Name]" (non-loggable, for AMRAP/EMOM/etc)
        if (preg_match('/^(\d+)\s+\[([^\]]+)\]$/', $trimmed, $matches)) {
            return [
                'type' => 'exercise',
                'name' => trim($matches[2]),
                'reps' => (int)$matches[1],
                'loggable' => false
            ];
        }
        
        // Format: "[[Exercise Name]]" (loggable, just the exercise, no scheme)
        if (preg_match('/^\[\[([^\]]+)\]\]$/', $trimmed, $matches)) {
            return [
                'type' => 'exercise',
                'name' => trim($matches[1]),
                'loggable' => true
            ];
        }
        
        // Format: "[Exercise Name]" (non-loggable, just the exercise, no scheme)
        if (preg_match('/^\[([^\]]+)\]$/', $trimmed, $matches)) {
            return [
                'type' => 'exercise',
                'name' => trim($matches[1]),
                'loggable' => false
            ];
        }
        
        // Format with colon: "[[Exercise Name]]: 3x8" (loggable)
        if (preg_match('/^\[\[([^\]]+)\]\]:\s*(.+)$/', $trimmed, $matches)) {
            $name = trim($matches[1]);
            $scheme = trim($matches[2]);
            
            if (empty($name) || empty($scheme)) {
                return null;
            }
            
            return [
                'type' => 'exercise',
                'name' => $name,
                'scheme' => $this->parseScheme($scheme),
                'loggable' => true
            ];
        }
        
        // Format with colon: "[Exercise Name]: 3x8" (non-loggable)
        if (preg_match('/^\[([^\]]+)\]:\s*(.+)$/', $trimmed, $matches)) {
            $name = trim($matches[1]);
            $scheme = trim($matches[2]);
            
            if (empty($name) || empty($scheme)) {
                return null;
            }
            
            return [
                'type' => 'exercise',
                'name' => $name,
                'scheme' => $this->parseScheme($scheme),
                'loggable' => false
            ];
        }
        
        // Format without colon but with text after: "[[Exercise Name]] any text here" (loggable)
        if (preg_match('/^\[\[([^\]]+)\]\]\s+(.+)$/', $trimmed, $matches)) {
            return [
                'type' => 'exercise',
                'name' => trim($matches[1]),
                'scheme' => $this->parseScheme($matches[2]),
                'loggable' => true
            ];
        }
        
        // Format without colon but with text after: "[Exercise Name] any text here" (non-loggable)
        if (preg_match('/^\[([^\]]+)\]\s+(.+)$/', $trimmed, $matches)) {
            return [
                'type' => 'exercise',
                'name' => trim($matches[1]),
                'scheme' => $this->parseScheme($matches[2]),
                'loggable' => false
            ];
        }
        
        return null;
    }
    
    /**
     * Parse rep/set scheme
     */
    private function parseScheme(string $scheme): array
    {
        $scheme = trim($scheme);
        
        // Format: 3x8 or 3 x 8
        if (preg_match('/^(\d+)\s*x\s*(\d+)$/i', $scheme, $matches)) {
            return [
                'type' => 'sets_x_reps',
                'sets' => (int)$matches[1],
                'reps' => (int)$matches[2],
                'display' => $matches[1] . 'x' . $matches[2]
            ];
        }
        
        // Format: 3x8-12 (range)
        if (preg_match('/^(\d+)\s*x\s*(\d+)-(\d+)$/i', $scheme, $matches)) {
            return [
                'type' => 'sets_x_rep_range',
                'sets' => (int)$matches[1],
                'reps_min' => (int)$matches[2],
                'reps_max' => (int)$matches[3],
                'display' => $matches[1] . 'x' . $matches[2] . '-' . $matches[3]
            ];
        }
        
        // Format: 5-5-5-3-3-1 (descending/ascending)
        if (preg_match('/^(\d+(-\d+)+)$/', $scheme, $matches)) {
            $reps = array_map('intval', explode('-', $matches[1]));
            return [
                'type' => 'rep_ladder',
                'reps' => $reps,
                'display' => implode('-', $reps)
            ];
        }
        
        // Format: single number (e.g., "5" or "1")
        if (preg_match('/^\d+$/', $scheme)) {
            return [
                'type' => 'single_set',
                'reps' => (int)$scheme,
                'display' => $scheme
            ];
        }
        
        // Format: time-based (e.g., "500m", "5min", "2:00")
        if (preg_match('/^(\d+)(m|min|km|cal|sec)$/i', $scheme, $matches)) {
            return [
                'type' => 'time_distance',
                'value' => (int)$matches[1],
                'unit' => strtolower($matches[2]),
                'display' => $scheme
            ];
        }
        
        // Format: time (e.g., "2:00", "1:30")
        if (preg_match('/^(\d+):(\d+)$/', $scheme, $matches)) {
            return [
                'type' => 'time',
                'minutes' => (int)$matches[1],
                'seconds' => (int)$matches[2],
                'display' => $scheme
            ];
        }
        
        // Fallback: store as-is
        return [
            'type' => 'custom',
            'display' => $scheme
        ];
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
        $loggable = $exercise['loggable'] ?? false;
        $brackets = $loggable ? '[[' . $name . ']]' : '[' . $name . ']';
        
        // If exercise has reps (from AMRAP/EMOM/etc), format as "10 [[Exercise]]" or "10 [Exercise]"
        if (isset($exercise['reps'])) {
            return $exercise['reps'] . ' ' . $brackets;
        }
        
        // If exercise has scheme, format as "[[Exercise]]: 3x8" or "[Exercise]: 3x8"
        if (isset($exercise['scheme'])) {
            $scheme = $exercise['scheme']['display'] ?? '';
            return $brackets . ': ' . $scheme;
        }
        
        // Just the exercise name
        return $brackets;
    }
}
