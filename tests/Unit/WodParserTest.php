<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\WodParser;

class WodParserTest extends TestCase
{
    private WodParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new WodParser();
    }

    public function test_parses_simple_workout()
    {
        $text = <<<WOD
# Strength
Back Squat: 5-5-5-5-5
Bench Press: 3x8
WOD;

        $result = $this->parser->parse($text);

        $this->assertCount(1, $result['blocks']);
        $this->assertEquals('Strength', $result['blocks'][0]['name']);
        $this->assertCount(2, $result['blocks'][0]['exercises']);
        
        $squat = $result['blocks'][0]['exercises'][0];
        $this->assertEquals('Back Squat', $squat['name']);
        $this->assertEquals('rep_ladder', $squat['scheme']['type']);
        $this->assertEquals([5, 5, 5, 5, 5], $squat['scheme']['reps']);
        
        $bench = $result['blocks'][0]['exercises'][1];
        $this->assertEquals('Bench Press', $bench['name']);
        $this->assertEquals('sets_x_reps', $bench['scheme']['type']);
        $this->assertEquals(3, $bench['scheme']['sets']);
        $this->assertEquals(8, $bench['scheme']['reps']);
    }

    public function test_parses_amrap()
    {
        $text = <<<WOD
# Conditioning
AMRAP 12min:
  10 Box Jumps
  15 Push-ups
  20 Air Squats
WOD;

        $result = $this->parser->parse($text);

        $this->assertCount(1, $result['blocks']);
        $block = $result['blocks'][0];
        $this->assertEquals('Conditioning', $block['name']);
        $this->assertCount(1, $block['exercises']);
        
        $amrap = $block['exercises'][0];
        $this->assertEquals('special_format', $amrap['type']);
        $this->assertEquals('AMRAP', $amrap['format']);
        $this->assertEquals(12, $amrap['duration']);
        $this->assertCount(3, $amrap['exercises']);
        
        $this->assertEquals('Box Jumps', $amrap['exercises'][0]['name']);
        $this->assertEquals(10, $amrap['exercises'][0]['reps']);
    }

    public function test_parses_multiple_blocks()
    {
        $text = <<<WOD
# Block 1: Strength
Back Squat: 5-5-5-5-5

# Block 2: Accessory
Dumbbell Row: 3x12
Face Pulls: 3x15-20

# Block 3: Conditioning
AMRAP 12min:
  10 Box Jumps
  15 Push-ups
WOD;

        $result = $this->parser->parse($text);

        $this->assertCount(3, $result['blocks']);
        $this->assertEquals('Block 1: Strength', $result['blocks'][0]['name']);
        $this->assertEquals('Block 2: Accessory', $result['blocks'][1]['name']);
        $this->assertEquals('Block 3: Conditioning', $result['blocks'][2]['name']);
    }

    public function test_parses_rep_range()
    {
        $text = <<<WOD
# Workout
Curls: 3x8-12
WOD;

        $result = $this->parser->parse($text);

        $exercise = $result['blocks'][0]['exercises'][0];
        $this->assertEquals('sets_x_rep_range', $exercise['scheme']['type']);
        $this->assertEquals(3, $exercise['scheme']['sets']);
        $this->assertEquals(8, $exercise['scheme']['reps_min']);
        $this->assertEquals(12, $exercise['scheme']['reps_max']);
    }

    public function test_parses_for_time()
    {
        $text = <<<WOD
# WOD
21-15-9 For Time:
  Thrusters
  Pull-ups
WOD;

        $result = $this->parser->parse($text);

        $format = $result['blocks'][0]['exercises'][0];
        $this->assertEquals('special_format', $format['type']);
        $this->assertEquals('For Time', $format['format']);
        $this->assertEquals('21-15-9', $format['rep_scheme']);
        $this->assertCount(2, $format['exercises']);
    }

    public function test_parses_rounds()
    {
        $text = <<<WOD
# WOD
5 Rounds:
  10 Push-ups
  20 Squats
WOD;

        $result = $this->parser->parse($text);

        $format = $result['blocks'][0]['exercises'][0];
        $this->assertEquals('special_format', $format['type']);
        $this->assertEquals('Rounds', $format['format']);
        $this->assertEquals(5, $format['rounds']);
    }

    public function test_ignores_comments()
    {
        $text = <<<WOD
# Workout
// This is a comment
Back Squat: 5x5
-- Another comment
Bench Press: 3x8
WOD;

        $result = $this->parser->parse($text);

        $this->assertCount(2, $result['blocks'][0]['exercises']);
    }

    public function test_parses_single_set()
    {
        $text = <<<WOD
# Max Effort
Deadlift: 1
WOD;

        $result = $this->parser->parse($text);

        $exercise = $result['blocks'][0]['exercises'][0];
        $this->assertEquals('single_set', $exercise['scheme']['type']);
        $this->assertEquals(1, $exercise['scheme']['reps']);
    }

    public function test_parses_time_distance()
    {
        $text = <<<WOD
# Cardio
Row: 500m
Run: 5min
WOD;

        $result = $this->parser->parse($text);

        $row = $result['blocks'][0]['exercises'][0];
        $this->assertEquals('time_distance', $row['scheme']['type']);
        $this->assertEquals(500, $row['scheme']['value']);
        $this->assertEquals('m', $row['scheme']['unit']);
        
        $run = $result['blocks'][0]['exercises'][1];
        $this->assertEquals('time_distance', $run['scheme']['type']);
        $this->assertEquals(5, $run['scheme']['value']);
        $this->assertEquals('min', $run['scheme']['unit']);
    }

    public function test_unparse_recreates_text()
    {
        $text = <<<WOD
# Strength
Back Squat: 5-5-5-5-5
Bench Press: 3x8

# Conditioning
AMRAP 12min:
  10 Box Jumps
  15 Push-ups

WOD;

        $parsed = $this->parser->parse($text);
        $unparsed = $this->parser->unparse($parsed);

        // Parse again to compare structure
        $reparsed = $this->parser->parse($unparsed);
        
        $this->assertEquals($parsed['blocks'], $reparsed['blocks']);
    }
}
