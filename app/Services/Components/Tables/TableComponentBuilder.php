<?php

namespace App\Services\Components\Tables;

/**
 * Table Component Builder
 * 
 * Builds a tabular CRUD list optimized for narrow screens.
 * Each row can have up to 3 lines of text with edit and delete actions.
 */
class TableComponentBuilder
{
    protected array $data = [
        'rows' => [],
        'emptyMessage' => '',
        'confirmMessages' => [],
        'ariaLabels' => [
            'section' => 'Data table',
            'editItem' => 'Edit item',
            'deleteItem' => 'Delete item'
        ]
    ];
    
    public function row(int $id, string $line1, ?string $line2 = null, ?string $line3 = null): TableRowBuilder
    {
        return new TableRowBuilder($this, $id, $line1, $line2, $line3);
    }
    
    public function addRow(array $row): self
    {
        $this->data['rows'][] = $row;
        return $this;
    }
    
    public function rows(array $rows): self
    {
        $this->data['rows'] = $rows;
        return $this;
    }
    
    public function emptyMessage(string $message): self
    {
        $this->data['emptyMessage'] = $message;
        return $this;
    }
    
    public function confirmMessage(string $key, string $message): self
    {
        $this->data['confirmMessages'][$key] = $message;
        return $this;
    }
    
    public function ariaLabel(string $label): self
    {
        $this->data['ariaLabels']['section'] = $label;
        return $this;
    }
    
    public function spacedRows(bool $spaced = true): self
    {
        $this->data['spacedRows'] = $spaced;
        return $this;
    }
    
    protected function hasCheckboxes(): bool
    {
        foreach ($this->data['rows'] as $row) {
            if (isset($row['checkbox']) && $row['checkbox']) {
                return true;
            }
        }
        return false;
    }
    
    protected function hasBadges(): bool
    {
        foreach ($this->data['rows'] as $row) {
            if (isset($row['badges']) && !empty($row['badges'])) {
                return true;
            }
        }
        return false;
    }
    
    public function build(): array
    {
        $component = [
            'type' => 'table',
            'data' => $this->data
        ];
        
        if ($this->hasCheckboxes()) {
            $component['requiresScript'] = 'table-bulk-selection';
        }
        
        return $component;
    }
}
