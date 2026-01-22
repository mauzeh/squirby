<?php

namespace App\Services\Components\Display;

/**
 * PR Records Table Component Builder
 * 
 * Builds a table displaying PR records with labels and values
 * Used to show current records or beaten PRs in lift log displays
 */
class PRRecordsTableComponentBuilder
{
    protected array $data;
    
    public function __construct(string $title)
    {
        $this->data = [
            'title' => $title,
            'icon' => null,
            'records' => [],
            'cssClass' => null,
            'footerLink' => null,
            'footerText' => null
        ];
    }
    
    /**
     * Set the icon for the table header
     * 
     * @param string $icon Emoji or icon to display
     * @return self
     */
    public function icon(string $icon): self
    {
        $this->data['icon'] = $icon;
        return $this;
    }
    
    /**
     * Add a record row to the table
     * 
     * @param string $label The label for the record (e.g., "1RM", "Volume", "5 Reps")
     * @param string $value The value to display (e.g., "200.0 lbs", "150.0 → 160.0 lbs")
     * @param string|null $comparison Optional comparison value (e.g., "180 lbs" for current lift)
     * @return self
     */
    public function record(string $label, string $value, ?string $comparison = null): self
    {
        $this->data['records'][] = [
            'label' => $label,
            'value' => $value,
            'comparison' => $comparison
        ];
        
        return $this;
    }
    
    /**
     * Add multiple records at once
     * 
     * @param array $records Array of ['label' => string, 'value' => string, 'comparison' => string|null]
     * @return self
     */
    public function records(array $records): self
    {
        foreach ($records as $record) {
            $this->record(
                $record['label'], 
                $record['value'],
                $record['comparison'] ?? null
            );
        }
        
        return $this;
    }
    
    /**
     * Set a CSS class for styling variants
     * 
     * @param string $cssClass CSS class name (e.g., 'pr-records-table--beaten', 'pr-records-table--current')
     * @return self
     */
    public function cssClass(string $cssClass): self
    {
        $this->data['cssClass'] = $cssClass;
        return $this;
    }
    
    /**
     * Convenience method to style as "beaten PRs" variant (green theme)
     * 
     * @return self
     */
    public function beaten(): self
    {
        return $this->cssClass('pr-records-table--beaten');
    }
    
    /**
     * Convenience method to style as "current records" variant (blue theme)
     * 
     * @return self
     */
    public function current(): self
    {
        return $this->cssClass('pr-records-table--current');
    }
    
    /**
     * Add a footer link to view more details
     * 
     * @param string $url The URL to link to
     * @param string $text The link text (default: "View history")
     * @return self
     */
    public function footerLink(string $url, string $text = 'View history'): self
    {
        $this->data['footerLink'] = $url;
        $this->data['footerText'] = $text;
        return $this;
    }
    
    /**
     * Build the component array
     * 
     * @return array
     */
    public function build(): array
    {
        return [
            'type' => 'pr-records-table',
            'data' => $this->data
        ];
    }
}
