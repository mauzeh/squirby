<?php

namespace Tests\Unit\Services;

use App\Services\ComponentBuilder;
use Tests\TestCase;

class FormComponentBuilderTest extends TestCase
{
    /** @test */
    public function it_can_build_segmented_field(): void
    {
        $form = ComponentBuilder::form('test-form', 'Test Form')
            ->segmentedField(
                'test_field',
                'Test Segmented Field',
                [
                    ['value' => 'opt1', 'label' => 'Option 1'],
                    ['value' => 'opt2', 'label' => 'Option 2'],
                ],
                'opt1'
            );

        $built = $form->build();

        $this->assertEquals('form', $built['type']);
        $this->assertEquals('test-form', $built['data']['id']);
        
        $fields = $built['data']['numericFields'];
        $this->assertCount(1, $fields);
        
        $field = $fields[0];
        $this->assertEquals('test-form-test_field', $field['id']);
        $this->assertEquals('test_field', $field['name']);
        $this->assertEquals('Test Segmented Field', $field['label']);
        $this->assertEquals('segmented', $field['type']);
        $this->assertEquals('opt1', $field['defaultValue']);
        $this->assertCount(2, $field['options']);
    }
}
