<?php

namespace Tests\Unit\Services\Components;

use App\Services\Components\Lists\ItemListComponentBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for ItemListComponentBuilder.
 *
 * This test focuses on the showCancelButton functionality and ensures
 * that the builder correctly sets the showCancelButton flag in the
 * component data structure.
 */
class ItemListComponentBuilderTest extends TestCase
{
    /**
     * Test that showCancelButton defaults to true.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function show_cancel_button_defaults_to_true()
    {
        $builder = new ItemListComponentBuilder();
        $component = $builder->build();

        $this->assertTrue($component['data']['showCancelButton']);
    }

    /**
     * Test that showCancelButton can be set to false.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function show_cancel_button_can_be_set_to_false()
    {
        $builder = new ItemListComponentBuilder();
        $component = $builder->showCancelButton(false)->build();

        $this->assertFalse($component['data']['showCancelButton']);
    }

    /**
     * Test that showCancelButton can be set to true explicitly.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function show_cancel_button_can_be_set_to_true_explicitly()
    {
        $builder = new ItemListComponentBuilder();
        $component = $builder->showCancelButton(true)->build();

        $this->assertTrue($component['data']['showCancelButton']);
    }

    /**
     * Test that showCancelButton method returns the builder for chaining.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function show_cancel_button_returns_builder_for_chaining()
    {
        $builder = new ItemListComponentBuilder();
        $result = $builder->showCancelButton(false);

        $this->assertSame($builder, $result);
    }

    /**
     * Test that showCancelButton works with method chaining.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function show_cancel_button_works_with_method_chaining()
    {
        $builder = new ItemListComponentBuilder();
        $component = $builder
            ->initialState('expanded')
            ->showCancelButton(false)
            ->filterPlaceholder('Search items...')
            ->build();

        $this->assertEquals('expanded', $component['data']['initialState']);
        $this->assertFalse($component['data']['showCancelButton']);
        $this->assertEquals('Search items...', $component['data']['filterPlaceholder']);
    }

    /**
     * Test that showCancelButton can be toggled multiple times.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function show_cancel_button_can_be_toggled_multiple_times()
    {
        $builder = new ItemListComponentBuilder();
        
        // Set to false, then back to true
        $component = $builder
            ->showCancelButton(false)
            ->showCancelButton(true)
            ->build();

        $this->assertTrue($component['data']['showCancelButton']);

        // Create new builder and set to true, then false
        $builder2 = new ItemListComponentBuilder();
        $component2 = $builder2
            ->showCancelButton(true)
            ->showCancelButton(false)
            ->build();

        $this->assertFalse($component2['data']['showCancelButton']);
    }

    /**
     * Test that the component type is correct.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function component_type_is_item_list()
    {
        $builder = new ItemListComponentBuilder();
        $component = $builder->build();

        $this->assertEquals('item-list', $component['type']);
    }

    /**
     * Test that all default data structure is present.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function default_data_structure_is_complete()
    {
        $builder = new ItemListComponentBuilder();
        $component = $builder->build();

        $expectedKeys = [
            'items',
            'filterPlaceholder',
            'noResultsMessage',
            'createForm',
            'initialState',
            'showCancelButton',
            'restrictHeight',
            'ariaLabels'
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $component['data'], "Missing key: {$key}");
        }
    }

    /**
     * Test integration with initialState method.
     *
     * This test ensures that when initialState is set to 'expanded',
     * the showCancelButton can still be controlled independently.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function show_cancel_button_works_independently_of_initial_state()
    {
        $builder = new ItemListComponentBuilder();

        // Test expanded state with cancel button hidden
        $expandedNoCancelComponent = $builder
            ->initialState('expanded')
            ->showCancelButton(false)
            ->build();

        $this->assertEquals('expanded', $expandedNoCancelComponent['data']['initialState']);
        $this->assertFalse($expandedNoCancelComponent['data']['showCancelButton']);

        // Test expanded state with cancel button shown
        $builder2 = new ItemListComponentBuilder();
        $expandedWithCancelComponent = $builder2
            ->initialState('expanded')
            ->showCancelButton(true)
            ->build();

        $this->assertEquals('expanded', $expandedWithCancelComponent['data']['initialState']);
        $this->assertTrue($expandedWithCancelComponent['data']['showCancelButton']);

        // Test collapsed state with cancel button hidden
        $builder3 = new ItemListComponentBuilder();
        $collapsedNoCancelComponent = $builder3
            ->initialState('collapsed')
            ->showCancelButton(false)
            ->build();

        $this->assertEquals('collapsed', $collapsedNoCancelComponent['data']['initialState']);
        $this->assertFalse($collapsedNoCancelComponent['data']['showCancelButton']);
    }
}