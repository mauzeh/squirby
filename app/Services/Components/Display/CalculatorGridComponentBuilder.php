<?php

namespace App\Services\Components\Display;

/**
 * Calculator Grid Component Builder
 * 
 * Builds a percentage-based calculator grid showing recommended weights
 * based on 1RM calculations for different rep ranges
 */
class CalculatorGridComponentBuilder
{
    protected array $data;
    
    public function __construct(string $title)
    {
        $this->data = [
            'title' => $title,
            'columns' => [],
            'percentages' => [],
            'ariaLabel' => $title
        ];
    }
    
    /**
     * Set the columns for the calculator grid
     * Each column represents a different rep range (e.g., 1x1, 1x2, 1x3)
     * 
     * @param array $columns Array of columns, each with 'label' and 'one_rep_max'
     * @return self
     */
    public function columns(array $columns): self
    {
        $this->data['columns'] = $columns;
        return $this;
    }
    
    /**
     * Set the percentage rows for the calculator grid
     * 
     * @param array $percentages Array of percentage values (e.g., [100, 95, 90, 85, ...])
     * @return self
     */
    public function percentages(array $percentages): self
    {
        $this->data['percentages'] = $percentages;
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
            'type' => 'calculator-grid',
            'data' => $this->data
        ];
    }
}
