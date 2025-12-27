<?php

namespace Tests\Unit\Services\Components;

use App\Services\Components\Interactive\TabsComponentBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for TabsComponentBuilder.
 *
 * This test covers tab creation, active tab management, component nesting,
 * script collection, and accessibility features.
 */
class TabsComponentBuilderTest extends TestCase
{
    /**
     * Test that a tabs component can be created with an ID.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function can_create_tabs_component_with_id()
    {
        $builder = new TabsComponentBuilder('test-tabs');
        $component = $builder->build();

        $this->assertEquals('tabs', $component['type']);
        $this->assertEquals('test-tabs', $component['data']['id']);
        $this->assertIsArray($component['data']['tabs']);
        $this->assertEmpty($component['data']['tabs']);
    }

    /**
     * Test that tabs can be added to the component.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function can_add_tabs_to_component()
    {
        $builder = new TabsComponentBuilder('test-tabs');
        $component = $builder
            ->tab('tab1', 'First Tab')
            ->tab('tab2', 'Second Tab')
            ->build();

        $this->assertCount(2, $component['data']['tabs']);
        
        $firstTab = $component['data']['tabs'][0];
        $this->assertEquals('tab1', $firstTab['id']);
        $this->assertEquals('First Tab', $firstTab['label']);
        $this->assertIsArray($firstTab['components']);
        $this->assertEmpty($firstTab['components']);
        $this->assertNull($firstTab['icon']);
    }

    /**
     * Test that the first tab is automatically set as active.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function first_tab_is_automatically_active()
    {
        $builder = new TabsComponentBuilder('test-tabs');
        $component = $builder
            ->tab('tab1', 'First Tab')
            ->tab('tab2', 'Second Tab')
            ->build();

        $this->assertEquals('tab1', $component['data']['activeTab']);
        $this->assertTrue($component['data']['tabs'][0]['active']);
        $this->assertFalse($component['data']['tabs'][1]['active']);
    }

    /**
     * Test that a specific tab can be set as active.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function can_set_specific_tab_as_active()
    {
        $builder = new TabsComponentBuilder('test-tabs');
        $component = $builder
            ->tab('tab1', 'First Tab')
            ->tab('tab2', 'Second Tab')
            ->activeTab('tab2')
            ->build();

        $this->assertEquals('tab2', $component['data']['activeTab']);
        $this->assertFalse($component['data']['tabs'][0]['active']);
        $this->assertTrue($component['data']['tabs'][1]['active']);
    }

    /**
     * Test that a tab can be explicitly marked as active during creation.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function can_mark_tab_as_active_during_creation()
    {
        $builder = new TabsComponentBuilder('test-tabs');
        $component = $builder
            ->tab('tab1', 'First Tab')
            ->tab('tab2', 'Second Tab', [], null, true)
            ->build();

        $this->assertEquals('tab2', $component['data']['activeTab']);
        $this->assertFalse($component['data']['tabs'][0]['active']);
        $this->assertTrue($component['data']['tabs'][1]['active']);
    }

    /**
     * Test that only one tab can be active at a time.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function only_one_tab_can_be_active()
    {
        $builder = new TabsComponentBuilder('test-tabs');
        $component = $builder
            ->tab('tab1', 'First Tab', [], null, true)
            ->tab('tab2', 'Second Tab', [], null, true)
            ->tab('tab3', 'Third Tab')
            ->build();

        // Last explicitly active tab should win
        $this->assertEquals('tab2', $component['data']['activeTab']);
        $this->assertFalse($component['data']['tabs'][0]['active']);
        $this->assertTrue($component['data']['tabs'][1]['active']);
        $this->assertFalse($component['data']['tabs'][2]['active']);
    }

    /**
     * Test that activeTab method overrides previous active states.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function active_tab_method_overrides_previous_states()
    {
        $builder = new TabsComponentBuilder('test-tabs');
        $component = $builder
            ->tab('tab1', 'First Tab', [], null, true)
            ->tab('tab2', 'Second Tab', [], null, true)
            ->tab('tab3', 'Third Tab')
            ->activeTab('tab3')
            ->build();

        $this->assertEquals('tab3', $component['data']['activeTab']);
        $this->assertFalse($component['data']['tabs'][0]['active']);
        $this->assertFalse($component['data']['tabs'][1]['active']);
        $this->assertTrue($component['data']['tabs'][2]['active']);
    }

    /**
     * Test that tabs can have icons.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function tabs_can_have_icons()
    {
        $builder = new TabsComponentBuilder('test-tabs');
        $component = $builder
            ->tab('tab1', 'First Tab', [], 'fa-home')
            ->tab('tab2', 'Second Tab', [], 'fa-user')
            ->build();

        $this->assertEquals('fa-home', $component['data']['tabs'][0]['icon']);
        $this->assertEquals('fa-user', $component['data']['tabs'][1]['icon']);
    }

    /**
     * Test that tabs can contain components.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function tabs_can_contain_components()
    {
        $components = [
            ['type' => 'text', 'data' => ['content' => 'Hello World']],
            ['type' => 'button', 'data' => ['label' => 'Click Me']]
        ];

        $builder = new TabsComponentBuilder('test-tabs');
        $component = $builder
            ->tab('tab1', 'First Tab', $components)
            ->build();

        $this->assertEquals($components, $component['data']['tabs'][0]['components']);
    }

    /**
     * Test that required scripts are collected from nested components.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function collects_required_scripts_from_nested_components()
    {
        $componentsWithScripts = [
            [
                'type' => 'chart',
                'data' => ['chartType' => 'line'],
                'requiresScript' => 'chart-component'
            ],
            [
                'type' => 'form',
                'data' => ['fields' => []],
                'requiresScript' => ['form-validation', 'form-submit']
            ]
        ];

        $builder = new TabsComponentBuilder('test-tabs');
        $component = $builder
            ->tab('tab1', 'First Tab', $componentsWithScripts)
            ->build();

        $expectedScripts = [
            'mobile-entry/tabs',
            'chart-component',
            'form-validation',
            'form-submit'
        ];

        $this->assertEquals($expectedScripts, $component['requiresScript']);
    }

    /**
     * Test that duplicate scripts are not included multiple times.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function does_not_include_duplicate_scripts()
    {
        $tab1Components = [
            [
                'type' => 'chart',
                'data' => ['chartType' => 'line'],
                'requiresScript' => 'chart-component'
            ]
        ];

        $tab2Components = [
            [
                'type' => 'chart',
                'data' => ['chartType' => 'bar'],
                'requiresScript' => 'chart-component'
            ]
        ];

        $builder = new TabsComponentBuilder('test-tabs');
        $component = $builder
            ->tab('tab1', 'First Tab', $tab1Components)
            ->tab('tab2', 'Second Tab', $tab2Components)
            ->build();

        $expectedScripts = [
            'mobile-entry/tabs',
            'chart-component'
        ];

        $this->assertEquals($expectedScripts, $component['requiresScript']);
    }

    /**
     * Test that aria labels can be customized.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function can_customize_aria_labels()
    {
        $customLabels = [
            'section' => 'Custom tabbed interface',
            'tabList' => 'Custom tab navigation',
            'tabPanel' => 'Custom tab content'
        ];

        $builder = new TabsComponentBuilder('test-tabs');
        $component = $builder
            ->ariaLabels($customLabels)
            ->build();

        $this->assertEquals($customLabels, $component['data']['ariaLabels']);
    }

    /**
     * Test that aria labels merge with defaults.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function aria_labels_merge_with_defaults()
    {
        $partialLabels = [
            'section' => 'Custom section label'
        ];

        $builder = new TabsComponentBuilder('test-tabs');
        $component = $builder
            ->ariaLabels($partialLabels)
            ->build();

        $expectedLabels = [
            'section' => 'Custom section label',
            'tabList' => 'Tab navigation',
            'tabPanel' => 'Tab content panel'
        ];

        $this->assertEquals($expectedLabels, $component['data']['ariaLabels']);
    }

    /**
     * Test that default aria labels are present.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function has_default_aria_labels()
    {
        $builder = new TabsComponentBuilder('test-tabs');
        $component = $builder->build();

        $expectedLabels = [
            'section' => 'Tabbed content',
            'tabList' => 'Tab navigation',
            'tabPanel' => 'Tab content panel'
        ];

        $this->assertEquals($expectedLabels, $component['data']['ariaLabels']);
    }

    /**
     * Test that methods return the builder for chaining.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function methods_return_builder_for_chaining()
    {
        $builder = new TabsComponentBuilder('test-tabs');

        $this->assertSame($builder, $builder->tab('tab1', 'First Tab'));
        $this->assertSame($builder, $builder->activeTab('tab1'));
        $this->assertSame($builder, $builder->ariaLabels([]));
    }

    /**
     * Test complex tab configuration with method chaining.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function supports_complex_configuration_with_chaining()
    {
        $tab1Components = [
            ['type' => 'text', 'data' => ['content' => 'Tab 1 content']]
        ];

        $tab2Components = [
            ['type' => 'button', 'data' => ['label' => 'Tab 2 button']]
        ];

        $customLabels = [
            'section' => 'Workout tabs'
        ];

        $builder = new TabsComponentBuilder('workout-tabs');
        $component = $builder
            ->tab('overview', 'Overview', $tab1Components, 'fa-eye')
            ->tab('details', 'Details', $tab2Components, 'fa-list', true)
            ->tab('history', 'History', [], 'fa-history')
            ->ariaLabels($customLabels)
            ->activeTab('history')
            ->build();

        // Verify structure
        $this->assertEquals('tabs', $component['type']);
        $this->assertEquals('workout-tabs', $component['data']['id']);
        $this->assertCount(3, $component['data']['tabs']);

        // Verify active tab
        $this->assertEquals('history', $component['data']['activeTab']);
        $this->assertFalse($component['data']['tabs'][0]['active']); // overview
        $this->assertFalse($component['data']['tabs'][1]['active']); // details
        $this->assertTrue($component['data']['tabs'][2]['active']);  // history

        // Verify tab details
        $overviewTab = $component['data']['tabs'][0];
        $this->assertEquals('overview', $overviewTab['id']);
        $this->assertEquals('Overview', $overviewTab['label']);
        $this->assertEquals('fa-eye', $overviewTab['icon']);
        $this->assertEquals($tab1Components, $overviewTab['components']);

        // Verify aria labels
        $expectedLabels = [
            'section' => 'Workout tabs',
            'tabList' => 'Tab navigation',
            'tabPanel' => 'Tab content panel'
        ];
        $this->assertEquals($expectedLabels, $component['data']['ariaLabels']);
    }

    /**
     * Test that tabs component always includes its own script.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function always_includes_tabs_script()
    {
        $builder = new TabsComponentBuilder('test-tabs');
        $component = $builder->build();

        $this->assertContains('mobile-entry/tabs', $component['requiresScript']);
    }

    /**
     * Test that empty tabs array is handled correctly.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function handles_empty_tabs_correctly()
    {
        $builder = new TabsComponentBuilder('test-tabs');
        $component = $builder->build();

        $this->assertEmpty($component['data']['tabs']);
        $this->assertNull($component['data']['activeTab']);
        $this->assertEquals(['mobile-entry/tabs'], $component['requiresScript']);
    }

    /**
     * Test that setting active tab to non-existent tab ID is handled.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function setting_non_existent_active_tab_updates_active_tab_property()
    {
        $builder = new TabsComponentBuilder('test-tabs');
        $component = $builder
            ->tab('tab1', 'First Tab')
            ->tab('tab2', 'Second Tab')
            ->activeTab('non-existent')
            ->build();

        // The activeTab property should be updated even if the tab doesn't exist
        $this->assertEquals('non-existent', $component['data']['activeTab']);
        
        // But existing tabs should all be marked as inactive
        $this->assertFalse($component['data']['tabs'][0]['active']);
        $this->assertFalse($component['data']['tabs'][1]['active']);
    }

    /**
     * Test that tab components without requiresScript are handled correctly.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function handles_components_without_required_scripts()
    {
        $componentsWithoutScripts = [
            ['type' => 'text', 'data' => ['content' => 'Hello']],
            ['type' => 'heading', 'data' => ['text' => 'Title']]
        ];

        $builder = new TabsComponentBuilder('test-tabs');
        $component = $builder
            ->tab('tab1', 'First Tab', $componentsWithoutScripts)
            ->build();

        $this->assertEquals(['mobile-entry/tabs'], $component['requiresScript']);
    }
}