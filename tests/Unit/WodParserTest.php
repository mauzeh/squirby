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
[Back Squat]: 5-5-5-5-5
[Bench Press]: 3x8
WOD;

        $result = $this->parser->parse($text);

        $this->assertCount(1, $result['blocks']);
        $this->assertEquals('Strength', $result['blocks'][0]['name']);
        $this->assertCount(2, $result['blocks'][0]['exercises']);
        
        $squat = $result['blocks'][0]['exercises'][0];
        $this->assertEquals('Back Squat', $squat['name']);
        $this->assertEquals('5-5-5-5-5', $squat['scheme']);
    }

    public function test_parses_special_format_with_exercises()
    {
        $text = <<<WOD
# Conditioning
> AMRAP 12min
10 [Box Jumps]
15 [Push-ups]
WOD;

        $result = $this->parser->parse($text);

        $format = $result['blocks'][0]['exercises'][0];
        $this->assertEquals('special_format', $format['type']);
        $this->assertEquals('AMRAP 12min', $format['description']);
        $this->assertCount(2, $format['exercises']);
    }

    public function test_parses_multiple_blocks()
    {
        $text = <<<WOD
# Block 1
[Back Squat]: 5x5

# Block 2
> AMRAP 12min
10 [Box Jumps]
WOD;

        $result = $this->parser->parse($text);

        $this->assertCount(2, $result['blocks']);
        $this->assertEquals('Block 1', $result['blocks'][0]['name']);
        $this->assertEquals('Block 2', $result['blocks'][1]['name']);
    }

    public function test_requires_brackets_around_exercise_names()
    {
        $text = <<<WOD
# Workout
Back Squat: 5x5
WOD;

        $result = $this->parser->parse($text);
        $this->assertCount(0, $result['blocks'][0]['exercises']);
    }

    public function test_parses_exercise_with_just_name_no_scheme()
    {
        $text = <<<WOD
# Warm-up
[Stretching]
[Mobility Work]
WOD;

        $result = $this->parser->parse($text);

        $this->assertCount(2, $result['blocks'][0]['exercises']);
        $this->assertEquals('Stretching', $result['blocks'][0]['exercises'][0]['name']);
        $this->assertArrayNotHasKey('scheme', $result['blocks'][0]['exercises'][0]);
    }

    public function test_parses_multiple_special_formats_in_same_block()
    {
        $text = <<<WOD
# WOD
> AMRAP 10min
10 [Burpees]
> 5 Rounds
5 [Pull-ups]
WOD;

        $result = $this->parser->parse($text);

        $this->assertCount(2, $result['blocks'][0]['exercises']);
        $this->assertEquals('AMRAP 10min', $result['blocks'][0]['exercises'][0]['description']);
        $this->assertEquals('5 Rounds', $result['blocks'][0]['exercises'][1]['description']);
    }

    public function test_special_format_ends_at_new_block()
    {
        $text = <<<WOD
# Block 1
> AMRAP 10min
10 [Burpees]
# Block 2
[Squats]: 3x10
WOD;

        $result = $this->parser->parse($text);

        $this->assertCount(2, $result['blocks']);
        $this->assertEquals('AMRAP 10min', $result['blocks'][0]['exercises'][0]['description']);
        $this->assertEquals('Squats', $result['blocks'][1]['exercises'][0]['name']);
    }

    public function test_parses_custom_format_descriptions()
    {
        $text = <<<WOD
# WOD
> Superset - no rest
[Pull-ups]: 3x8
[Dips]: 3x10
WOD;

        $result = $this->parser->parse($text);

        $format = $result['blocks'][0]['exercises'][0];
        $this->assertEquals('Superset - no rest', $format['description']);
        $this->assertCount(2, $format['exercises']);
    }

    public function test_unparse_preserves_special_format_structure()
    {
        $text = <<<WOD
# WOD
> AMRAP 12min
10 [Burpees]
WOD;

        $parsed = $this->parser->parse($text);
        $unparsed = $this->parser->unparse($parsed);

        $this->assertStringContainsString('> AMRAP 12min', $unparsed);
        $this->assertStringContainsString('10 [Burpees]', $unparsed);
    }

    public function test_parses_block_headers_without_space_after_hash()
    {
        $text = <<<'WOD'
#Block 1
[Squats]: 3x10
##Block 2
[Deadlifts]: 5x5
WOD;

        $result = $this->parser->parse($text);

        $this->assertCount(2, $result['blocks']);
        $this->assertEquals('Block 1', $result['blocks'][0]['name']);
        $this->assertEquals('Block 2', $result['blocks'][1]['name']);
    }

    public function test_parses_double_bracket_exercises_as_loggable()
    {
        $text = <<<WOD
# Workout
[[Back Squat]]: 5x5
[Warm-up Squats]: 2x10
WOD;

        $result = $this->parser->parse($text);

        $this->assertCount(2, $result['blocks'][0]['exercises']);
        
        $loggable = $result['blocks'][0]['exercises'][0];
        $this->assertEquals('Back Squat', $loggable['name']);
        $this->assertTrue($loggable['loggable']);
        
        $nonLoggable = $result['blocks'][0]['exercises'][1];
        $this->assertEquals('Warm-up Squats', $nonLoggable['name']);
        $this->assertFalse($nonLoggable['loggable']);
    }

    public function test_parses_double_bracket_in_special_formats()
    {
        $text = <<<WOD
# WOD
> AMRAP 12min
10 [[Box Jumps]]
15 [Push-ups]
WOD;

        $result = $this->parser->parse($text);

        $format = $result['blocks'][0]['exercises'][0];
        $this->assertCount(2, $format['exercises']);
        
        $loggable = $format['exercises'][0];
        $this->assertEquals('Box Jumps', $loggable['name']);
        $this->assertTrue($loggable['loggable']);
        
        $nonLoggable = $format['exercises'][1];
        $this->assertEquals('Push-ups', $nonLoggable['name']);
        $this->assertFalse($nonLoggable['loggable']);
    }

    public function test_unparse_preserves_loggable_flag()
    {
        $text = <<<WOD
# Workout
[[Back Squat]]: 5x5
[Warm-up]: 2x10
WOD;

        $parsed = $this->parser->parse($text);
        $unparsed = $this->parser->unparse($parsed);

        $this->assertStringContainsString('[[Back Squat]]', $unparsed);
        $this->assertStringContainsString('[Warm-up]', $unparsed);
    }

    public function test_parses_double_bracket_with_freeform_text()
    {
        $text = <<<WOD
# Workout
[[Back Squat]] 5 reps, building
[[Deadlift]] work up to heavy single
WOD;

        $result = $this->parser->parse($text);

        $this->assertCount(2, $result['blocks'][0]['exercises']);
        
        $squat = $result['blocks'][0]['exercises'][0];
        $this->assertEquals('Back Squat', $squat['name']);
        $this->assertTrue($squat['loggable']);
        $this->assertEquals('5 reps, building', $squat['scheme']);
        
        $deadlift = $result['blocks'][0]['exercises'][1];
        $this->assertEquals('Deadlift', $deadlift['name']);
        $this->assertTrue($deadlift['loggable']);
        $this->assertEquals('work up to heavy single', $deadlift['scheme']);
    }

    public function test_parses_single_bracket_with_freeform_text()
    {
        $text = <<<WOD
# Workout
[Stretching] 5 minutes
[Mobility Work] as needed
WOD;

        $result = $this->parser->parse($text);

        $this->assertCount(2, $result['blocks'][0]['exercises']);
        
        $stretching = $result['blocks'][0]['exercises'][0];
        $this->assertEquals('Stretching', $stretching['name']);
        $this->assertFalse($stretching['loggable']);
        $this->assertEquals('5 minutes', $stretching['scheme']);
        
        $mobility = $result['blocks'][0]['exercises'][1];
        $this->assertEquals('Mobility Work', $mobility['name']);
        $this->assertFalse($mobility['loggable']);
        $this->assertEquals('as needed', $mobility['scheme']);
    }

    public function test_parses_markdown_list_formats()
    {
        $text = <<<WOD
# Workout
* [[Back Squat]] 5 reps, building
- [[Deadlift]] 5x5
+ [[Bench Press]] 3x8
1. [[Pull-ups]] 3x10
2. 10 [[Box Jumps]]
  * 15 [[Push-ups]]
WOD;

        $result = $this->parser->parse($text);

        $this->assertCount(6, $result['blocks'][0]['exercises']);
        
        // Unordered list with asterisk
        $squat = $result['blocks'][0]['exercises'][0];
        $this->assertEquals('Back Squat', $squat['name']);
        $this->assertTrue($squat['loggable']);
        $this->assertEquals('5 reps, building', $squat['scheme']);
        
        // Unordered list with dash
        $deadlift = $result['blocks'][0]['exercises'][1];
        $this->assertEquals('Deadlift', $deadlift['name']);
        $this->assertTrue($deadlift['loggable']);
        $this->assertEquals('5x5', $deadlift['scheme']);
        
        // Unordered list with plus
        $bench = $result['blocks'][0]['exercises'][2];
        $this->assertEquals('Bench Press', $bench['name']);
        $this->assertTrue($bench['loggable']);
        
        // Ordered list
        $pullups = $result['blocks'][0]['exercises'][3];
        $this->assertEquals('Pull-ups', $pullups['name']);
        $this->assertTrue($pullups['loggable']);
        
        // Ordered list with reps before exercise
        $boxJumps = $result['blocks'][0]['exercises'][4];
        $this->assertEquals('Box Jumps', $boxJumps['name']);
        $this->assertEquals(10, $boxJumps['reps']);
        $this->assertTrue($boxJumps['loggable']);
        
        // Indented list with reps before exercise
        $pushups = $result['blocks'][0]['exercises'][5];
        $this->assertEquals('Push-ups', $pushups['name']);
        $this->assertEquals(15, $pushups['reps']);
        $this->assertTrue($pushups['loggable']);
    }

    public function test_parses_markdown_lists_with_non_loggable_exercises()
    {
        $text = <<<WOD
# Warm-up
* [Stretching] 5 minutes
- [Mobility Work] as needed
1. [Foam Rolling] 2 minutes
WOD;

        $result = $this->parser->parse($text);

        $this->assertCount(3, $result['blocks'][0]['exercises']);
        
        $stretching = $result['blocks'][0]['exercises'][0];
        $this->assertEquals('Stretching', $stretching['name']);
        $this->assertFalse($stretching['loggable']);
        
        $mobility = $result['blocks'][0]['exercises'][1];
        $this->assertEquals('Mobility Work', $mobility['name']);
        $this->assertFalse($mobility['loggable']);
        
        $foam = $result['blocks'][0]['exercises'][2];
        $this->assertEquals('Foam Rolling', $foam['name']);
        $this->assertFalse($foam['loggable']);
    }

    public function test_parses_markdown_lists_in_special_formats()
    {
        $text = <<<WOD
# WOD
> AMRAP 12min
* 10 [[Box Jumps]]
- 15 [[Push-ups]]
1. 20 [[Air Squats]]
> 5 Rounds
* 5 [[Pull-ups]]
- 10 [Sit-ups]
WOD;

        $result = $this->parser->parse($text);

        $this->assertCount(2, $result['blocks'][0]['exercises']);
        
        // First special format
        $amrap = $result['blocks'][0]['exercises'][0];
        $this->assertEquals('AMRAP 12min', $amrap['description']);
        $this->assertCount(3, $amrap['exercises']);
        
        $this->assertEquals('Box Jumps', $amrap['exercises'][0]['name']);
        $this->assertEquals(10, $amrap['exercises'][0]['reps']);
        $this->assertTrue($amrap['exercises'][0]['loggable']);
        
        $this->assertEquals('Push-ups', $amrap['exercises'][1]['name']);
        $this->assertEquals(15, $amrap['exercises'][1]['reps']);
        $this->assertTrue($amrap['exercises'][1]['loggable']);
        
        $this->assertEquals('Air Squats', $amrap['exercises'][2]['name']);
        $this->assertEquals(20, $amrap['exercises'][2]['reps']);
        $this->assertTrue($amrap['exercises'][2]['loggable']);
        
        // Second special format
        $rounds = $result['blocks'][0]['exercises'][1];
        $this->assertEquals('5 Rounds', $rounds['description']);
        $this->assertCount(2, $rounds['exercises']);
        
        $this->assertEquals('Pull-ups', $rounds['exercises'][0]['name']);
        $this->assertTrue($rounds['exercises'][0]['loggable']);
        
        $this->assertEquals('Sit-ups', $rounds['exercises'][1]['name']);
        $this->assertFalse($rounds['exercises'][1]['loggable']);
    }

    public function test_parses_deeply_nested_markdown_lists()
    {
        $text = <<<WOD
# Workout
* [[Exercise 1]] 3x8
  * [[Exercise 2]] 3x10
    * [[Exercise 3]] 3x12
      * [[Exercise 4]] 3x15
WOD;

        $result = $this->parser->parse($text);

        $this->assertCount(4, $result['blocks'][0]['exercises']);
        
        foreach ($result['blocks'][0]['exercises'] as $index => $exercise) {
            $this->assertEquals('Exercise ' . ($index + 1), $exercise['name']);
            $this->assertTrue($exercise['loggable']);
        }
    }

    public function test_parses_high_numbered_ordered_lists()
    {
        $text = <<<WOD
# Workout
99. [[Exercise 1]] 3x8
100. [[Exercise 2]] 3x10
1234. [[Exercise 3]] 3x12
WOD;

        $result = $this->parser->parse($text);

        $this->assertCount(3, $result['blocks'][0]['exercises']);
        
        $this->assertEquals('Exercise 1', $result['blocks'][0]['exercises'][0]['name']);
        $this->assertEquals('Exercise 2', $result['blocks'][0]['exercises'][1]['name']);
        $this->assertEquals('Exercise 3', $result['blocks'][0]['exercises'][2]['name']);
    }

    public function test_parses_mixed_loggable_and_non_loggable_in_markdown_lists()
    {
        $text = <<<WOD
# Workout
* [[Back Squat]] 5x5
* [Warm-up Squats] 2x10
* [[Deadlift]] 3x5
* [Stretching] 5 minutes
WOD;

        $result = $this->parser->parse($text);

        $this->assertCount(4, $result['blocks'][0]['exercises']);
        
        $this->assertTrue($result['blocks'][0]['exercises'][0]['loggable']);
        $this->assertFalse($result['blocks'][0]['exercises'][1]['loggable']);
        $this->assertTrue($result['blocks'][0]['exercises'][2]['loggable']);
        $this->assertFalse($result['blocks'][0]['exercises'][3]['loggable']);
    }

    public function test_parses_markdown_lists_with_various_scheme_formats()
    {
        $text = <<<WOD
# Workout
* [[Exercise 1]] 3x8
- [[Exercise 2]] 5-5-5-3-3-1
+ [[Exercise 3]] 3x8-12
1. [[Exercise 4]] 5 reps, building
2. [[Exercise 5]] work up to heavy single
3. [[Exercise 6]] 500m
4. [[Exercise 7]] 2:00
WOD;

        $result = $this->parser->parse($text);

        $this->assertCount(7, $result['blocks'][0]['exercises']);
        
        // Various scheme formats stored as strings
        $this->assertEquals('3x8', $result['blocks'][0]['exercises'][0]['scheme']);
        $this->assertEquals('5-5-5-3-3-1', $result['blocks'][0]['exercises'][1]['scheme']);
        $this->assertEquals('3x8-12', $result['blocks'][0]['exercises'][2]['scheme']);
        $this->assertEquals('5 reps, building', $result['blocks'][0]['exercises'][3]['scheme']);
        $this->assertEquals('work up to heavy single', $result['blocks'][0]['exercises'][4]['scheme']);
        $this->assertEquals('500m', $result['blocks'][0]['exercises'][5]['scheme']);
        $this->assertEquals('2:00', $result['blocks'][0]['exercises'][6]['scheme']);
    }

    public function test_parses_markdown_lists_with_colon_syntax()
    {
        $text = <<<WOD
# Workout
* [[Back Squat]]: 5x5
- [[Deadlift]]: 3x8
1. [[Bench Press]]: 3x10
WOD;

        $result = $this->parser->parse($text);

        $this->assertCount(3, $result['blocks'][0]['exercises']);
        
        $this->assertEquals('Back Squat', $result['blocks'][0]['exercises'][0]['name']);
        $this->assertEquals('5x5', $result['blocks'][0]['exercises'][0]['scheme']);
        
        $this->assertEquals('Deadlift', $result['blocks'][0]['exercises'][1]['name']);
        $this->assertEquals('3x8', $result['blocks'][0]['exercises'][1]['scheme']);
        
        $this->assertEquals('Bench Press', $result['blocks'][0]['exercises'][2]['name']);
        $this->assertEquals('3x10', $result['blocks'][0]['exercises'][2]['scheme']);
    }

    public function test_parses_markdown_lists_with_just_exercise_names()
    {
        $text = <<<WOD
# Workout
* [[Back Squat]]
- [[Deadlift]]
+ [[Bench Press]]
1. [[Pull-ups]]
WOD;

        $result = $this->parser->parse($text);

        $this->assertCount(4, $result['blocks'][0]['exercises']);
        
        foreach ($result['blocks'][0]['exercises'] as $exercise) {
            $this->assertTrue($exercise['loggable']);
            $this->assertArrayNotHasKey('scheme', $exercise);
            $this->assertArrayNotHasKey('reps', $exercise);
        }
    }

    public function test_real_world_wod_with_markdown()
    {
        $text = <<<WOD
# 5 Rounds, Every 3 min:
* [[Back Squat]] 5 reps, building  
* [[Deadlift]] 5 reps, building    

# METCON
## 5 Rounds, 15-min time cap:
* 10 [[Box Jumps]]  
* 10 [[Push-Ups]]    
* 10 [Nothingness]
WOD;

        $result = $this->parser->parse($text);

        $this->assertCount(3, $result['blocks']);
        
        // First block
        $this->assertEquals('5 Rounds, Every 3 min:', $result['blocks'][0]['name']);
        $this->assertCount(2, $result['blocks'][0]['exercises']);
        $this->assertTrue($result['blocks'][0]['exercises'][0]['loggable']);
        $this->assertTrue($result['blocks'][0]['exercises'][1]['loggable']);
        
        // Second block (empty METCON header)
        $this->assertEquals('METCON', $result['blocks'][1]['name']);
        $this->assertCount(0, $result['blocks'][1]['exercises']);
        
        // Third block
        $this->assertEquals('5 Rounds, 15-min time cap:', $result['blocks'][2]['name']);
        $this->assertCount(3, $result['blocks'][2]['exercises']);
        $this->assertEquals(10, $result['blocks'][2]['exercises'][0]['reps']);
        $this->assertTrue($result['blocks'][2]['exercises'][0]['loggable']);
        $this->assertEquals(10, $result['blocks'][2]['exercises'][1]['reps']);
        $this->assertTrue($result['blocks'][2]['exercises'][1]['loggable']);
        $this->assertEquals(10, $result['blocks'][2]['exercises'][2]['reps']);
        $this->assertFalse($result['blocks'][2]['exercises'][2]['loggable']);
    }
}
