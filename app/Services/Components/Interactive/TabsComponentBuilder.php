<?php

namespace App\Services\Components\Interactive;

/**
 * Tabs Component Builder
 * 
 * Creates a tabbed interface with multiple content panels.
 * Each tab can contain any combination of other components.
 */
class TabsComponentBuilder
{
    protected array $data;
    
    public function __construct(string $id)
    {
        $this->data = [
            'id' => $id,
            'tabs' => [],
            'activeTab' => null,
            'ariaLabels' => [
                'section' => 'Tabbed content',
                'tabList' => 'Tab navigation',
                'tabPanel' => 'Tab content panel'
            ]
        ];
    }
    
    /**
     * Add a tab with its content
     * 
     * @param string $id Tab identifier
     * @param string $label Tab display label
     * @param array $components Array of component data for this tab
     * @param string|null $icon Optional FontAwesome icon class
     * @param bool $active Whether this tab should be active by default
     */
    public function tab(string $id, string $label, array $components = [], ?string $icon = null, bool $active = false): self
    {
        $tab = [
            'id' => $id,
            'label' => $label,
            'components' => $components,
            'icon' => $icon,
            'active' => $active
        ];
        
        // Set as active tab if this is the first tab or explicitly marked active
        if ($active || empty($this->data['tabs'])) {
            $this->data['activeTab'] = $id;
            $tab['active'] = true;
            
            // Unmark other tabs as active
            foreach ($this->data['tabs'] as &$existingTab) {
                $existingTab['active'] = false;
            }
        }
        
        $this->data['tabs'][] = $tab;
        return $this;
    }
    
    /**
     * Set which tab should be active by default
     */
    public function activeTab(string $tabId): self
    {
        $this->data['activeTab'] = $tabId;
        
        // Update tab active states
        foreach ($this->data['tabs'] as &$tab) {
            $tab['active'] = ($tab['id'] === $tabId);
        }
        
        return $this;
    }
    
    /**
     * Set aria labels for accessibility
     */
    public function ariaLabels(array $labels): self
    {
        $this->data['ariaLabels'] = array_merge($this->data['ariaLabels'], $labels);
        return $this;
    }
    
    /**
     * Build the component data
     */
    public function build(): array
    {
        // Collect required scripts from nested tab components
        $requiredScripts = ['mobile-entry/tabs'];
        
        foreach ($this->data['tabs'] as $tab) {
            foreach ($tab['components'] as $component) {
                if (isset($component['requiresScript'])) {
                    $scripts = is_array($component['requiresScript']) 
                        ? $component['requiresScript'] 
                        : [$component['requiresScript']];
                    
                    foreach ($scripts as $script) {
                        if (!in_array($script, $requiredScripts)) {
                            $requiredScripts[] = $script;
                        }
                    }
                }
            }
        }
        
        return [
            'type' => 'tabs',
            'data' => $this->data,
            'requiresScript' => $requiredScripts
        ];
    }
}