<?php

namespace App\Services\Components\Charts;

/**
 * Chart Component Builder
 * 
 * Builds Chart.js charts for the flexible component system
 */
class ChartComponentBuilder
{
    protected array $data;
    
    public function __construct(string $canvasId, string $title)
    {
        $this->data = [
            'canvasId' => $canvasId,
            'title' => $title,
            'type' => 'line',
            'datasets' => [],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => true,
            ],
            'height' => null,
            'containerClass' => 'form-container',
            'ariaLabel' => $title . ' chart'
        ];
    }
    
    public function type(string $type): self
    {
        $this->data['type'] = $type;
        return $this;
    }
    
    public function datasets(array $datasets): self
    {
        $this->data['datasets'] = $datasets;
        return $this;
    }
    
    public function options(array $options): self
    {
        $this->data['options'] = array_merge($this->data['options'], $options);
        return $this;
    }
    
    public function height(int $pixels): self
    {
        $this->data['height'] = $pixels;
        return $this;
    }
    
    public function containerClass(string $class): self
    {
        $this->data['containerClass'] = $class;
        return $this;
    }
    
    public function ariaLabel(string $label): self
    {
        $this->data['ariaLabel'] = $label;
        return $this;
    }
    
    public function timeScale(string $unit = 'day', ?string $displayFormat = null): self
    {
        if (!isset($this->data['options']['scales'])) {
            $this->data['options']['scales'] = [];
        }
        
        $this->data['options']['scales']['x'] = [
            'type' => 'time',
            'time' => ['unit' => $unit]
        ];
        
        if ($displayFormat) {
            $this->data['options']['scales']['x']['time']['displayFormats'] = [
                $unit => $displayFormat
            ];
        }
        
        return $this;
    }
    
    public function beginAtZero(bool $value = true): self
    {
        if (!isset($this->data['options']['scales'])) {
            $this->data['options']['scales'] = [];
        }
        
        if (!isset($this->data['options']['scales']['y'])) {
            $this->data['options']['scales']['y'] = [];
        }
        
        $this->data['options']['scales']['y']['beginAtZero'] = $value;
        return $this;
    }
    
    public function showLegend(bool $value = true): self
    {
        if (!isset($this->data['options']['plugins'])) {
            $this->data['options']['plugins'] = [];
        }
        
        if (!isset($this->data['options']['plugins']['legend'])) {
            $this->data['options']['plugins']['legend'] = [];
        }
        
        $this->data['options']['plugins']['legend']['display'] = $value;
        return $this;
    }
    
    public function yAxisLabel(string $label): self
    {
        if (!isset($this->data['options']['scales'])) {
            $this->data['options']['scales'] = [];
        }
        
        if (!isset($this->data['options']['scales']['y'])) {
            $this->data['options']['scales']['y'] = [];
        }
        
        $this->data['options']['scales']['y']['title'] = [
            'display' => true,
            'text' => $label
        ];
        
        return $this;
    }
    
    public function xAxisLabel(string $label): self
    {
        if (!isset($this->data['options']['scales'])) {
            $this->data['options']['scales'] = [];
        }
        
        if (!isset($this->data['options']['scales']['x'])) {
            $this->data['options']['scales']['x'] = [];
        }
        
        $this->data['options']['scales']['x']['title'] = [
            'display' => true,
            'text' => $label
        ];
        
        return $this;
    }
    
    public function noAspectRatio(): self
    {
        $this->data['options']['maintainAspectRatio'] = false;
        return $this;
    }
    
    public function labelColors(string $color = '#e0e0e0'): self
    {
        if (!isset($this->data['options']['scales'])) {
            $this->data['options']['scales'] = [];
        }
        
        // X-axis labels
        if (!isset($this->data['options']['scales']['x'])) {
            $this->data['options']['scales']['x'] = [];
        }
        $this->data['options']['scales']['x']['ticks'] = [
            'color' => $color
        ];
        $this->data['options']['scales']['x']['grid'] = [
            'color' => 'rgba(255, 255, 255, 0.1)'
        ];
        
        // Y-axis labels
        if (!isset($this->data['options']['scales']['y'])) {
            $this->data['options']['scales']['y'] = [];
        }
        $this->data['options']['scales']['y']['ticks'] = [
            'color' => $color
        ];
        $this->data['options']['scales']['y']['grid'] = [
            'color' => 'rgba(255, 255, 255, 0.1)'
        ];
        
        // Legend labels
        if (!isset($this->data['options']['plugins'])) {
            $this->data['options']['plugins'] = [];
        }
        if (!isset($this->data['options']['plugins']['legend'])) {
            $this->data['options']['plugins']['legend'] = [];
        }
        $this->data['options']['plugins']['legend']['labels'] = [
            'color' => $color
        ];
        
        return $this;
    }
    
    public function build(): array
    {
        return [
            'type' => 'chart',
            'data' => $this->data,
            'requiresScript' => 'chart-component'
        ];
    }
}
