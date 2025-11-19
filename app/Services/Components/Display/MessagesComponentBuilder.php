<?php

namespace App\Services\Components\Display;

/**
 * Messages Component Builder
 */
class MessagesComponentBuilder
{
    protected array $data = [
        'messages' => []
    ];
    
    public function add(string $type, string $text, ?string $prefix = null): self
    {
        $message = ['type' => $type, 'text' => $text];
        
        if ($prefix) {
            $message['prefix'] = $prefix;
        }
        
        $this->data['messages'][] = $message;
        return $this;
    }
    
    public function success(string $text, ?string $prefix = null): self
    {
        return $this->add('success', $text, $prefix);
    }
    
    public function error(string $text, ?string $prefix = null): self
    {
        return $this->add('error', $text, $prefix);
    }
    
    public function warning(string $text, ?string $prefix = null): self
    {
        return $this->add('warning', $text, $prefix);
    }
    
    public function info(string $text, ?string $prefix = null): self
    {
        return $this->add('info', $text, $prefix);
    }
    
    public function tip(string $text, ?string $prefix = null): self
    {
        return $this->add('tip', $text, $prefix);
    }
    
    public function build(): array
    {
        return [
            'type' => 'messages',
            'data' => $this->data
        ];
    }
}
