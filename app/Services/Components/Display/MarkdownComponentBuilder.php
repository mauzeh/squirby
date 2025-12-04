<?php

namespace App\Services\Components\Display;

/**
 * Markdown Component Builder
 * 
 * Renders markdown content in a styled container
 */
class MarkdownComponentBuilder
{
    protected array $data;
    
    public function __construct(string $markdown)
    {
        $this->data = [
            'markdown' => $markdown,
            'classes' => ''
        ];
    }
    
    /**
     * Add custom CSS classes
     */
    public function classes(string $classes): self
    {
        $this->data['classes'] = $classes;
        return $this;
    }
    
    public function build(): array
    {
        return [
            'type' => 'markdown',
            'data' => $this->data
        ];
    }
}
