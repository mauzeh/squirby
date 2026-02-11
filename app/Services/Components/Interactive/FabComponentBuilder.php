<?php

namespace App\Services\Components\Interactive;

class FabComponentBuilder
{
    protected string $url;
    protected string $icon;
    protected ?string $tooltip = null;
    protected ?string $title = null;

    public function __construct(string $url, string $icon)
    {
        $this->url = $url;
        $this->icon = $icon;
    }

    public function tooltip(string $tooltip): self
    {
        $this->tooltip = $tooltip;
        return $this;
    }

    public function title(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function build(): array
    {
        return [
            'type' => 'fab',
            'data' => [
                'url' => $this->url,
                'icon' => $this->icon,
                'tooltip' => $this->tooltip,
                'title' => $this->title,
            ]
        ];
    }
}
