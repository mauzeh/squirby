<?php

namespace Tests\Unit\Services\Components\Tables;

use App\Services\Components\Tables\TableComponentBuilder;
use App\Services\Components\Tables\TableRowBuilder;
use Tests\TestCase;

class TableRowBuilderTest extends TestCase
{
    /** @test */
    public function it_builds_basic_row()
    {
        $table = new TableComponentBuilder();
        $table->row(1, 'Title', 'Subtitle', 'Description')->add();
        
        $result = $table->build();
        
        $this->assertEquals('table', $result['type']);
        $this->assertCount(1, $result['data']['rows']);
        $this->assertEquals('Title', $result['data']['rows'][0]['line1']);
        $this->assertEquals('Subtitle', $result['data']['rows'][0]['line2']);
        $this->assertEquals('Description', $result['data']['rows'][0]['line3']);
    }
    
    /** @test */
    public function it_adds_clickable_url_to_row()
    {
        $table = new TableComponentBuilder();
        $table->row(1, 'Title', 'Subtitle')
            ->clickable('https://example.com/edit/1')
            ->add();
        
        $result = $table->build();
        
        $this->assertArrayHasKey('clickableUrl', $result['data']['rows'][0]);
        $this->assertEquals('https://example.com/edit/1', $result['data']['rows'][0]['clickableUrl']);
    }
    
    /** @test */
    public function it_does_not_add_clickable_url_when_not_set()
    {
        $table = new TableComponentBuilder();
        $table->row(1, 'Title', 'Subtitle')->add();
        
        $result = $table->build();
        
        $this->assertArrayNotHasKey('clickableUrl', $result['data']['rows'][0]);
    }
    
    /** @test */
    public function clickable_row_can_still_have_actions()
    {
        $table = new TableComponentBuilder();
        $table->row(1, 'Title', 'Subtitle')
            ->clickable('https://example.com/edit/1')
            ->formAction('fa-trash', 'https://example.com/delete/1', 'DELETE', [], 'Delete', 'btn-danger', true)
            ->add();
        
        $result = $table->build();
        
        $this->assertArrayHasKey('clickableUrl', $result['data']['rows'][0]);
        $this->assertCount(1, $result['data']['rows'][0]['actions']);
        $this->assertEquals('form', $result['data']['rows'][0]['actions'][0]['type']);
    }
    
    /** @test */
    public function it_adds_link_action_to_row()
    {
        $table = new TableComponentBuilder();
        $table->row(1, 'Title', 'Subtitle')
            ->linkAction('fa-edit', 'https://example.com/edit/1', 'Edit item', 'btn-primary')
            ->add();
        
        $result = $table->build();
        
        $this->assertCount(1, $result['data']['rows'][0]['actions']);
        $this->assertEquals('link', $result['data']['rows'][0]['actions'][0]['type']);
        $this->assertEquals('fa-edit', $result['data']['rows'][0]['actions'][0]['icon']);
        $this->assertEquals('https://example.com/edit/1', $result['data']['rows'][0]['actions'][0]['url']);
    }
    
    /** @test */
    public function it_adds_form_action_to_row()
    {
        $table = new TableComponentBuilder();
        $table->row(1, 'Title', 'Subtitle')
            ->formAction('fa-trash', 'https://example.com/delete/1', 'DELETE', ['key' => 'value'], 'Delete item', 'btn-danger', true)
            ->add();
        
        $result = $table->build();
        
        $this->assertCount(1, $result['data']['rows'][0]['actions']);
        $action = $result['data']['rows'][0]['actions'][0];
        $this->assertEquals('form', $action['type']);
        $this->assertEquals('fa-trash', $action['icon']);
        $this->assertEquals('https://example.com/delete/1', $action['url']);
        $this->assertEquals('DELETE', $action['method']);
        $this->assertEquals(['key' => 'value'], $action['params']);
        $this->assertTrue($action['requiresConfirm']);
    }
    
    /** @test */
    public function it_sets_compact_mode()
    {
        $table = new TableComponentBuilder();
        $table->row(1, 'Title', 'Subtitle')
            ->compact()
            ->add();
        
        $result = $table->build();
        
        $this->assertTrue($result['data']['rows'][0]['compact']);
    }
    
    /** @test */
    public function it_adds_badges_to_row()
    {
        $table = new TableComponentBuilder();
        $table->row(1, 'Title', 'Subtitle')
            ->badge('New', 'success', true)
            ->badge('Hot', 'danger')
            ->add();
        
        $result = $table->build();
        
        $this->assertCount(2, $result['data']['rows'][0]['badges']);
        $this->assertEquals('New', $result['data']['rows'][0]['badges'][0]['text']);
        $this->assertEquals('success', $result['data']['rows'][0]['badges'][0]['colorClass']);
        $this->assertTrue($result['data']['rows'][0]['badges'][0]['emphasized']);
    }
    
    /** @test */
    public function it_adds_messages_to_row()
    {
        $table = new TableComponentBuilder();
        $table->row(1, 'Title', 'Subtitle')
            ->message('success', 'Operation completed', 'Success:')
            ->add();
        
        $result = $table->build();
        
        $this->assertCount(1, $result['data']['rows'][0]['messages']);
        $this->assertEquals('success', $result['data']['rows'][0]['messages'][0]['type']);
        $this->assertEquals('Operation completed', $result['data']['rows'][0]['messages'][0]['text']);
        $this->assertEquals('Success:', $result['data']['rows'][0]['messages'][0]['prefix']);
    }
}
