<?php

namespace App\Services\MobileEntry;

use App\Services\ComponentBuilder;

/**
 * Assembles UI components for mobile entry pages
 * Handles navigation, title, messages, summary, buttons, item lists, and logged items
 */
class ComponentAssembler
{
    /**
     * Assemble all components for a mobile entry page
     * 
     * @param string $entryType Type of entry page (lifts, foods, measurements)
     * @param array $dateContext Date context with navigation dates and title
     * @param array $serviceData Data from domain services (logged items, selection list, etc)
     * @param array $config Configuration options
     * @return array Array of component data structures
     */
    public function assemble(
        string $entryType,
        array $dateContext,
        array $serviceData,
        array $config
    ): array {
        $components = [];
        
        $components[] = $this->buildNavigation($entryType, $dateContext);
        $components[] = $this->buildTitle($dateContext['title']);
        
        if ($config['isFirstTimeUser'] ?? false) {
            $components[] = $this->buildWelcomeOverlay();
        }
        
        if ($serviceData['interfaceMessages']['hasMessages'] ?? false) {
            $components[] = $this->buildMessages($serviceData['interfaceMessages']);
        }
        
        if ($serviceData['summary'] ?? null) {
            $components[] = $this->buildSummary($serviceData['summary']);
        }
        
        if ($config['showAddButton'] ?? true) {
            $components[] = $this->buildLogNowButton($entryType);
        }
        
        // For measurements, add forms instead of item list
        if ($entryType === 'measurements' && isset($serviceData['forms'])) {
            $components = array_merge($components, $serviceData['forms']);
        } elseif ($config['showItemList'] ?? true) {
            $components[] = $this->buildItemSelectionList(
                $serviceData['itemSelectionList'],
                $config['shouldExpandSelection'] ?? false
            );
        }
        
        $components[] = $serviceData['loggedItems'];
        
        // Add FAB for quick connection
        $currentUser = auth()->user();
        $hasConnections = $currentUser->following()->exists() || $currentUser->followers()->exists();
        $fab = ComponentBuilder::fab(route('connections.index'), 'fa-user-plus')
            ->title('Connect');
        
        if (!$hasConnections) {
            $fab->tooltip('Connect with friends');
        }
        
        $components[] = $fab->build();
        
        return $components;
    }
    
    /**
     * Build navigation component
     */
    private function buildNavigation(string $entryType, array $dateContext): array
    {
        $routeName = "mobile-entry.{$entryType}";
        
        return ComponentBuilder::navigation()
            ->prev('← Prev', route($routeName, ['date' => $dateContext['prevDay']->toDateString()]))
            ->center('Today', route($routeName, ['date' => $dateContext['today']->toDateString()]))
            ->next('Next →', route($routeName, ['date' => $dateContext['nextDay']->toDateString()]), 
                   $dateContext['selectedDate']->lt($dateContext['today']))
            ->ariaLabel('Date navigation')
            ->build();
    }
    
    /**
     * Build title component
     */
    private function buildTitle(array $titleData): array
    {
        return ComponentBuilder::title(
            $titleData['main'],
            $titleData['subtitle'] ?? null
        )->build();
    }
    
    /**
     * Build welcome overlay for first-time users
     */
    private function buildWelcomeOverlay(): array
    {
        return [
            'type' => 'welcome-overlay',
            'requiresScript' => 'welcome-overlay',
            'data' => [
                'show' => true,
                'userName' => auth()->user()->name,
                'title' => 'Let\'s Get Strong!',
                'message' => 'Congratulations on signing up! This is where you\'ll track your workouts and watch your strength grow over time.',
                'ctaText' => 'Start Logging Now!'
            ]
        ];
    }
    
    /**
     * Build messages component
     */
    private function buildMessages(array $interfaceMessages): array
    {
        $builder = ComponentBuilder::messages();
        foreach ($interfaceMessages['messages'] as $message) {
            $builder->add($message['type'], $message['text'], $message['prefix'] ?? null);
        }
        return $builder->build();
    }
    
    /**
     * Build summary component
     */
    private function buildSummary(array $summary): array
    {
        $builder = ComponentBuilder::summary();
        foreach ($summary['values'] as $key => $value) {
            $builder->item($key, $value, $summary['labels'][$key] ?? null);
        }
        return $builder->build();
    }
    
    /**
     * Build "Log Now" button
     */
    private function buildLogNowButton(string $entryType): array
    {
        $label = match($entryType) {
            'lifts' => 'Add new exercise',
            'foods' => 'Add new food item',
            default => 'Add new item'
        };
        
        return ComponentBuilder::button('Log Now')
            ->ariaLabel($label)
            ->addClass('btn-add-item')
            ->addClass('btn-log-now')
            ->icon('fa-plus')
            ->build();
    }
    
    /**
     * Build item selection list component
     */
    private function buildItemSelectionList(array $itemSelectionList, bool $shouldExpand): array
    {
        $builder = ComponentBuilder::itemList()
            ->filterPlaceholder($itemSelectionList['filterPlaceholder'])
            ->noResultsMessage($itemSelectionList['noResultsMessage']);
        
        if ($shouldExpand) {
            $builder->initialState('expanded')->showCancelButton(false);
        }
        
        foreach ($itemSelectionList['items'] as $item) {
            $builder->item(
                $item['id'],
                $item['name'],
                $item['href'],
                $item['type']['label'],
                $item['type']['cssClass'],
                $item['type']['priority']
            );
        }
        
        if (isset($itemSelectionList['createForm'])) {
            $builder->createForm(
                $itemSelectionList['createForm']['action'],
                $itemSelectionList['createForm']['inputName'],
                $itemSelectionList['createForm']['hiddenFields'],
                $itemSelectionList['createForm']['buttonTextTemplate'] ?? 'Create "{term}"',
                $itemSelectionList['createForm']['method'] ?? 'POST'
            );
        }
        
        return $builder->build();
    }
}
