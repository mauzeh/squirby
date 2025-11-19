<?php

namespace App\Services\Components\Display;

/**
 * Title Component Builder
 */
class TitleComponentBuilder
{
    protected array $data;
    
    public function __construct(string $main, ?string $subtitle = null)
    {
        $this->data = [
            'main' => $main,
            'subtitle' => $subtitle,
            'backButton' => null
        ];
    }
    
    public function subtitle(string $subtitle): self
    {
        $this->data['subtitle'] = $subtitle;
        return $this;
    }
    
    public function backButton(string $icon, string $url, ?string $ariaLabel = null): self
    {
        $this->data['backButton'] = [
            'icon' => $icon,
            'url' => $url,
            'ariaLabel' => $ariaLabel ?? 'Go back'
        ];
        return $this;
    }
    
    public function build(): array
    {
        return [
            'type' => 'title',
            'data' => $this->data
        ];
    }
}
