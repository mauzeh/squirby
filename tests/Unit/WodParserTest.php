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
        $this->assertEquals('rep_ladder', $squat['scheme']['type']);
        $this->assertEquals([5, 5, 5, 5, 5], $squat['scheme']['reps']);
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
}
