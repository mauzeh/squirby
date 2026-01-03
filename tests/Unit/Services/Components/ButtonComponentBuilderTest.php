<?php

namespace Tests\Unit\Services\Components;

use App\Services\Components\Interactive\ButtonComponentBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for ButtonComponentBuilder.
 *
 * This test covers all functionality of the ButtonComponentBuilder including
 * the new icon functionality, CSS class handling, link conversion, and
 * method chaining behavior.
 */
class ButtonComponentBuilderTest extends TestCase
{
    /**
     * Test that a basic button is created with default values.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function creates_basic_button_with_defaults()
    {
        $builder = new ButtonComponentBuilder('Test Button');
        $component = $builder->build();

        $this->assertEquals('button', $component['type']);
        $this->assertEquals('Test Button', $component['data']['text']);
        $this->assertEquals('Test Button', $component['data']['ariaLabel']);
        $this->assertEquals('btn-primary', $component['data']['cssClass']);
        $this->assertEquals('button', $component['data']['type']);
        $this->assertEquals('visible', $component['data']['initialState']);
        $this->assertNull($component['data']['icon']);
    }

    /**
     * Test that icon can be set and is included in component data.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function icon_can_be_set()
    {
        $builder = new ButtonComponentBuilder('Log Now');
        $component = $builder->icon('fa-plus')->build();

        $this->assertEquals('fa-plus', $component['data']['icon']);
    }

    /**
     * Test that icon defaults to null when not set.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function icon_defaults_to_null()
    {
        $builder = new ButtonComponentBuilder('Test Button');
        $component = $builder->build();

        $this->assertNull($component['data']['icon']);
    }

    /**
     * Test that icon method returns builder for chaining.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function icon_returns_builder_for_chaining()
    {
        $builder = new ButtonComponentBuilder('Test Button');
        $result = $builder->icon('fa-plus');

        $this->assertSame($builder, $result);
    }

    /**
     * Test that aria label can be customized.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function aria_label_can_be_customized()
    {
        $builder = new ButtonComponentBuilder('Log Now');
        $component = $builder->ariaLabel('Add new exercise')->build();

        $this->assertEquals('Add new exercise', $component['data']['ariaLabel']);
    }

    /**
     * Test that CSS class can be set.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function css_class_can_be_set()
    {
        $builder = new ButtonComponentBuilder('Test Button');
        $component = $builder->cssClass('btn-secondary')->build();

        $this->assertEquals('btn-secondary', $component['data']['cssClass']);
    }

    /**
     * Test that CSS classes can be added to existing class.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function css_classes_can_be_added()
    {
        $builder = new ButtonComponentBuilder('Test Button');
        $component = $builder->addClass('btn-add-item')->build();

        $this->assertEquals('btn-primary btn-add-item', $component['data']['cssClass']);
    }

    /**
     * Test that multiple CSS classes can be added.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function multiple_css_classes_can_be_added()
    {
        $builder = new ButtonComponentBuilder('Test Button');
        $component = $builder
            ->addClass('btn-add-item')
            ->addClass('btn-large')
            ->build();

        $this->assertEquals('btn-primary btn-add-item btn-large', $component['data']['cssClass']);
    }

    /**
     * Test that button can be converted to link.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function button_can_be_converted_to_link()
    {
        $builder = new ButtonComponentBuilder('Test Button');
        $component = $builder->asLink('https://example.com')->build();

        $this->assertEquals('link', $component['data']['type']);
        $this->assertEquals('https://example.com', $component['data']['url']);
    }

    /**
     * Test that url method works as alias for asLink.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function url_method_works_as_alias_for_as_link()
    {
        $builder = new ButtonComponentBuilder('Test Button');
        $component = $builder->url('https://example.com')->build();

        $this->assertEquals('link', $component['data']['type']);
        $this->assertEquals('https://example.com', $component['data']['url']);
    }

    /**
     * Test that style method sets predefined CSS classes.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function style_method_sets_predefined_css_classes()
    {
        $builder = new ButtonComponentBuilder('Test Button');
        
        $primaryComponent = $builder->style('primary')->build();
        $this->assertEquals('btn-primary', $primaryComponent['data']['cssClass']);

        $builder2 = new ButtonComponentBuilder('Test Button');
        $secondaryComponent = $builder2->style('secondary')->build();
        $this->assertEquals('btn-secondary', $secondaryComponent['data']['cssClass']);

        $builder3 = new ButtonComponentBuilder('Test Button');
        $outlineComponent = $builder3->style('outline')->build();
        $this->assertEquals('btn-outline', $outlineComponent['data']['cssClass']);

        $builder4 = new ButtonComponentBuilder('Test Button');
        $dangerComponent = $builder4->style('danger')->build();
        $this->assertEquals('btn-danger', $dangerComponent['data']['cssClass']);
    }

    /**
     * Test that unknown style defaults to primary.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function unknown_style_defaults_to_primary()
    {
        $builder = new ButtonComponentBuilder('Test Button');
        $component = $builder->style('unknown')->build();

        $this->assertEquals('btn-primary', $component['data']['cssClass']);
    }

    /**
     * Test that initial state can be set.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function initial_state_can_be_set()
    {
        $builder = new ButtonComponentBuilder('Test Button');
        $component = $builder->initialState('hidden')->build();

        $this->assertEquals('hidden', $component['data']['initialState']);
    }

    /**
     * Test complex method chaining with all features.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function complex_method_chaining_works()
    {
        $builder = new ButtonComponentBuilder('Log Now');
        $component = $builder
            ->icon('fa-plus')
            ->ariaLabel('Add new exercise')
            ->addClass('btn-add-item')
            ->asLink('https://example.com/create')
            ->initialState('visible')
            ->build();

        $this->assertEquals('button', $component['type']);
        $this->assertEquals('Log Now', $component['data']['text']);
        $this->assertEquals('fa-plus', $component['data']['icon']);
        $this->assertEquals('Add new exercise', $component['data']['ariaLabel']);
        $this->assertEquals('btn-primary btn-add-item', $component['data']['cssClass']);
        $this->assertEquals('link', $component['data']['type']);
        $this->assertEquals('https://example.com/create', $component['data']['url']);
        $this->assertEquals('visible', $component['data']['initialState']);
    }

    /**
     * Test that all builder methods return the builder instance for chaining.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function all_methods_return_builder_for_chaining()
    {
        $builder = new ButtonComponentBuilder('Test Button');

        $this->assertSame($builder, $builder->icon('fa-plus'));
        $this->assertSame($builder, $builder->ariaLabel('Test Label'));
        $this->assertSame($builder, $builder->cssClass('test-class'));
        $this->assertSame($builder, $builder->addClass('additional-class'));
        $this->assertSame($builder, $builder->asLink('https://example.com'));
        $this->assertSame($builder, $builder->url('https://example.com'));
        $this->assertSame($builder, $builder->style('primary'));
        $this->assertSame($builder, $builder->initialState('hidden'));
    }

    /**
     * Test that icon can be changed multiple times.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function icon_can_be_changed_multiple_times()
    {
        $builder = new ButtonComponentBuilder('Test Button');
        $component = $builder
            ->icon('fa-plus')
            ->icon('fa-edit')
            ->build();

        $this->assertEquals('fa-edit', $component['data']['icon']);
    }

    /**
     * Test that component data structure is complete.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function component_data_structure_is_complete()
    {
        $builder = new ButtonComponentBuilder('Test Button');
        $component = $builder->build();

        $expectedKeys = [
            'text',
            'ariaLabel',
            'cssClass',
            'type',
            'initialState',
            'icon'
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $component['data'], "Missing key: {$key}");
        }
    }

    /**
     * Test that link buttons include URL in data.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function link_buttons_include_url_in_data()
    {
        $builder = new ButtonComponentBuilder('Test Button');
        $component = $builder->asLink('https://example.com')->build();

        $this->assertArrayHasKey('url', $component['data']);
        $this->assertEquals('https://example.com', $component['data']['url']);
    }

    /**
     * Test that regular buttons do not include URL in data.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function regular_buttons_do_not_include_url_in_data()
    {
        $builder = new ButtonComponentBuilder('Test Button');
        $component = $builder->build();

        $this->assertArrayNotHasKey('url', $component['data']);
    }
}