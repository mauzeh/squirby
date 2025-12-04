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
 * Back Squat: 5-5-5-5-5
 * Bench Press: 3x8
 * 
 * # Block 2: Conditioning
 * AMRAP 12min:
 *   10 Box Jumps
 *   15 Push-ups
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
            
            // Check for special format (AMRAP, EMOM, For Time, Rounds)
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
        return preg_match('/^#{1,3}\s+/', $line);
    }
    
    /**
     * Parse block name from header
     */
    private function parseBlockName(string $line): string
    {
        return trim(preg_replace('/^#{1,3}\s+/', '', $line));
    }
    
    /**
     * Check if line is a special format
     */
    private function isSpecialFormat(string $line): bool
    {
        $trimmed = trim($line);
        return preg_match('/^(AMRAP|EMOM|For Time|[0-9]+ Rounds?)[\s:]/i', $trimmed) ||
               preg_match('/^[0-9]+-[0-9]+-[0-9]+\s+(For Time|Rounds?)[\s:]/i', $trimmed);
    }
    
    /**
     * Parse special format line
     */
    private function parseSpecialFormat(string $line): array
    {
        $trimmed = trim($line);
        
        // AMRAP Xmin:
        if (preg_match('/^AMRAP\s+(\d+)\s*min/i', $trimmed, $matches)) {
            return [
                'type' => 'special_format',
                'format' => 'AMRAP',
                'duration' => (int)$matches[1],
                'exercises' => []
            ];
        }
        
        // EMOM Xmin:
        if (preg_match('/^EMOM\s+(\d+)\s*min/i', $trimmed, $matches)) {
            return [
                'type' => 'special_format',
                'format' => 'EMOM',
                'duration' => (int)$matches[1],
                'exercises' => []
            ];
        }
        
        // For Time:
        if (preg_match('/^For Time/i', $trimmed)) {
            return [
                'type' => 'special_format',
                'format' => 'For Time',
                'exercises' => []
            ];
        }
        
        // X Rounds: or X Round:
        if (preg_match('/^(\d+)\s+Rounds?[\s:]/i', $trimmed, $matches)) {
            return [
                'type' => 'special_format',
                'format' => 'Rounds',
                'rounds' => (int)$matches[1],
                'exercises' => []
            ];
        }
        
        // 21-15-9 For Time: or similar
        if (preg_match('/^([0-9-]+)\s+(For Time|Rounds?)[\s:]/i', $trimmed, $matches)) {
            return [
                'type' => 'special_format',
                'format' => $matches[2],
                'rep_scheme' => $matches[1],
                'exercises' => []
            ];
        }
        
        return [
            'type' => 'special_format',
            'format' => 'Custom',
            'description' => rtrim($trimmed, ':'),
            'exercises' => []
        ];
    }
    
    /**
     * Parse regular exercise line
     */
    private function parseExercise(string $line): ?array
    {
        $trimmed = trim($line);
        
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
                'scheme' => $this->parseScheme($scheme)
            ];
        }
        
        // Format without colon: "[Exercise Name] 3x8" or "[Exercise Name] 3-3-3"
        if (preg_match('/^\[([^\]]+)\]\s+((?:\d+x\d+(?:-\d+)?|\d+(?:-\d+)+))$/i', $trimmed, $matches)) {
            return [
                'type' => 'exercise',
                'name' => trim($matches[1]),
                'scheme' => $this->parseScheme($matches[2])
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
        if ($format['format'] === 'AMRAP' && isset($format['duration'])) {
            return 'AMRAP ' . $format['duration'] . 'min:';
        }
        
        if ($format['format'] === 'EMOM' && isset($format['duration'])) {
            return 'EMOM ' . $format['duration'] . 'min:';
        }
        
        if ($format['format'] === 'For Time') {
            if (isset($format['rep_scheme'])) {
                return $format['rep_scheme'] . ' For Time:';
            }
            return 'For Time:';
        }
        
        if ($format['format'] === 'Rounds' && isset($format['rounds'])) {
            return $format['rounds'] . ' Rounds:';
        }
        
        return $format['description'] ?? 'Custom Format:';
    }
    
    private function unparseExercise(array $exercise): string
    {
        $name = $exercise['name'];
        
        // If exercise has reps (from AMRAP/EMOM/etc), format as "10 [Exercise]"
        if (isset($exercise['reps'])) {
            return $exercise['reps'] . ' [' . $name . ']';
        }
        
        // If exercise has scheme, format as "[Exercise]: 3x8"
        if (isset($exercise['scheme'])) {
            $scheme = $exercise['scheme']['display'] ?? '';
            return '[' . $name . ']: ' . $scheme;
        }
        
        // Just the exercise name
        return '[' . $name . ']';
    }
}
