<?php

namespace App\Services;

use App\Services\Components\Navigation\NavigationComponentBuilder;
use App\Services\Components\Display\TitleComponentBuilder;
use App\Services\Components\Display\MessagesComponentBuilder;
use App\Services\Components\Display\SummaryComponentBuilder;
use App\Services\Components\Display\PRCardsComponentBuilder;
use App\Services\Components\Display\CalculatorGridComponentBuilder;
use App\Services\Components\Display\MarkdownComponentBuilder;
use App\Services\Components\Interactive\ButtonComponentBuilder;
use App\Services\Components\Interactive\FormComponentBuilder;
use App\Services\Components\Interactive\BulkActionFormComponentBuilder;
use App\Services\Components\Interactive\SelectAllControlComponentBuilder;
use App\Services\Components\Interactive\CodeEditorComponentBuilder;
use App\Services\Components\Interactive\QuickActionsComponentBuilder;
use App\Services\Components\Interactive\TabsComponentBuilder;
use App\Services\Components\Lists\ItemListComponentBuilder;
use App\Services\Components\Tables\TableComponentBuilder;
use App\Services\Components\Charts\ChartComponentBuilder;

/**
 * Component Builder
 * 
 * Fluent interface for building UI components for the flexible mobile entry view.
 * Each component type has its own builder class for type-safe configuration.
 */
class ComponentBuilder
{
    /**
     * Create a navigation component
     */
    public static function navigation(): NavigationComponentBuilder
    {
        return new NavigationComponentBuilder();
    }
    
    /**
     * Create a title component
     */
    public static function title(string $main, ?string $subtitle = null): TitleComponentBuilder
    {
        return new TitleComponentBuilder($main, $subtitle);
    }
    
    /**
     * Create a messages component
     */
    public static function messages(): MessagesComponentBuilder
    {
        return new MessagesComponentBuilder();
    }
    
    /**
     * Create a messages component from session flash data
     * Returns null if no session messages exist
     */
    public static function messagesFromSession(): ?array
    {
        $builder = new MessagesComponentBuilder();
        $hasMessages = false;
        
        if (session('success')) {
            $builder->success(session('success'));
            $hasMessages = true;
        }
        
        if (session('error')) {
            $builder->error(session('error'));
            $hasMessages = true;
        }
        
        if (session('warning')) {
            $builder->warning(session('warning'));
            $hasMessages = true;
        }
        
        if (session('info')) {
            $builder->info(session('info'));
            $hasMessages = true;
        }
        
        return $hasMessages ? $builder->build() : null;
    }
    
    /**
     * Create a summary component
     */
    public static function summary(): SummaryComponentBuilder
    {
        return new SummaryComponentBuilder();
    }
    
    /**
     * Create a button component
     */
    public static function button(string $text): ButtonComponentBuilder
    {
        return new ButtonComponentBuilder($text);
    }
    
    /**
     * Create an item list component
     */
    public static function itemList(): ItemListComponentBuilder
    {
        return new ItemListComponentBuilder();
    }
    
    /**
     * Create a form component
     */
    public static function form(string $id, string $title): FormComponentBuilder
    {
        return new FormComponentBuilder($id, $title);
    }
    
    /**
     * Create a table component
     */
    public static function table(): TableComponentBuilder
    {
        return new TableComponentBuilder();
    }
    
    /**
     * Create a bulk action form component
     */
    public static function bulkActionForm(string $formId, string $action, string $buttonText): BulkActionFormComponentBuilder
    {
        return new BulkActionFormComponentBuilder($formId, $action, $buttonText);
    }
    
    /**
     * Create a select all control component
     */
    public static function selectAllControl(string $checkboxId, string $label = 'Select All'): SelectAllControlComponentBuilder
    {
        return new SelectAllControlComponentBuilder($checkboxId, $label);
    }
    
    /**
     * Create a raw HTML component
     */
    public static function rawHtml(string $html): array
    {
        return [
            'type' => 'raw_html',
            'data' => ['html' => $html]
        ];
    }
    
    /**
     * Create a markdown component
     */
    public static function markdown(string $markdown): MarkdownComponentBuilder
    {
        return new MarkdownComponentBuilder($markdown);
    }
    
    /**
     * Create a chart component
     */
    public static function chart(string $canvasId, string $title): ChartComponentBuilder
    {
        return new ChartComponentBuilder($canvasId, $title);
    }
    
    /**
     * Create a PR cards component
     */
    public static function prCards(string $title): PRCardsComponentBuilder
    {
        return new PRCardsComponentBuilder($title);
    }
    
    /**
     * Create a calculator grid component
     */
    public static function calculatorGrid(string $title): CalculatorGridComponentBuilder
    {
        return new CalculatorGridComponentBuilder($title);
    }
    
    /**
     * Create a code editor component
     */
    public static function codeEditor(string $id, string $label): CodeEditorComponentBuilder
    {
        return new CodeEditorComponentBuilder($id, $label);
    }
    
    /**
     * Create a quick actions component
     */
    public static function quickActions(string $title = 'Quick Actions'): QuickActionsComponentBuilder
    {
        return new QuickActionsComponentBuilder($title);
    }
    
    /**
     * Create a tabs component
     */
    public static function tabs(string $id): TabsComponentBuilder
    {
        return new TabsComponentBuilder($id);
    }
}
