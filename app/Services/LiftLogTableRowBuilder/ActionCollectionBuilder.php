<?php

namespace App\Services\LiftLogTableRowBuilder;

use App\Models\LiftLog;

/**
 * Builder for creating action collections
 * Provides fluent API for adding actions to a row
 */
class ActionCollectionBuilder
{
    private array $actions = [];
    
    public function __construct(
        private LiftLog $liftLog,
        private RowConfig $config
    ) {}
    
    /**
     * Add a "View logs" action
     */
    public function addViewLogsAction(): self
    {
        $url = route('exercises.show-logs', $this->liftLog->exercise);
        
        if ($this->config->redirectContext === 'mobile-entry-lifts') {
            $url = $this->appendQueryParams($url, [
                'from' => $this->config->redirectContext,
                'date' => $this->config->selectedDate,
            ]);
        }
        
        $this->actions[] = [
            'type' => 'link',
            'url' => $url,
            'icon' => 'fa-chart-line',
            'ariaLabel' => 'View logs',
            'cssClass' => 'btn-info-circle'
        ];
        
        return $this;
    }
    
    /**
     * Add an "Edit" action
     */
    public function addEditAction(): self
    {
        $url = route('lift-logs.edit', $this->liftLog);
        
        if ($this->config->redirectContext) {
            $url = $this->appendQueryParams($url, [
                'redirect_to' => $this->config->redirectContext,
                'date' => $this->config->selectedDate ?? now()->toDateString(),
            ]);
        }
        
        $this->actions[] = [
            'type' => 'link',
            'url' => $url,
            'icon' => 'fa-pencil',
            'ariaLabel' => 'Edit',
            'cssClass' => 'btn-transparent'
        ];
        
        return $this;
    }
    
    /**
     * Add a "Delete" action
     */
    public function addDeleteAction(): self
    {
        $params = [];
        if ($this->config->redirectContext) {
            $params = [
                'redirect_to' => $this->config->redirectContext,
                'date' => $this->config->selectedDate ?? now()->toDateString(),
            ];
        }
        
        $this->actions[] = [
            'type' => 'form',
            'url' => route('lift-logs.destroy', $this->liftLog),
            'method' => 'DELETE',
            'icon' => 'fa-trash',
            'ariaLabel' => 'Delete',
            'cssClass' => 'btn-transparent',
            'requiresConfirm' => true,
            'params' => $params
        ];
        
        return $this;
    }
    
    /**
     * Build and return the actions array
     */
    public function build(): array
    {
        return $this->actions;
    }
    
    /**
     * Append query parameters to URL
     */
    private function appendQueryParams(string $url, array $params): string
    {
        $params = array_filter($params); // Remove nulls
        return empty($params) ? $url : $url . '?' . http_build_query($params);
    }
}
