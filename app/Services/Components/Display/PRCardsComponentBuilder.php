<?php

namespace App\Services\Components\Display;

/**
 * PR Cards Component Builder
 * 
 * Builds a grid of cards displaying personal records (PRs) for different rep ranges
 */
class PRCardsComponentBuilder
{
    protected array $data;
    
    public function __construct(string $title)
    {
        $this->data = [
            'title' => $title,
            'cards' => [],
            'ariaLabel' => $title
        ];
    }
    
    /**
     * Add a card to the PR cards grid
     * 
     * @param string $label The label for the card (e.g., "1x1", "1x2", "1x3")
     * @param mixed $value The value to display (weight or null for "—")
     * @param string|null $unit The unit to display (e.g., "lbs", "kg")
     * @param string|null $date The date when this PR was achieved (optional)
     * @return self
     */
    public function card(string $label, $value, ?string $unit = null, ?string $date = null): self
    {
        $this->data['cards'][] = [
            'label' => $label,
            'value' => $value,
            'unit' => $unit,
            'date' => $date
        ];
        
        return $this;
    }
    
    /**
     * Set the aria label for accessibility
     * 
     * @param string $label
     * @return self
     */
    public function ariaLabel(string $label): self
    {
        $this->data['ariaLabel'] = $label;
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
            'type' => 'pr-cards',
            'data' => $this->data
        ];
    }
}
