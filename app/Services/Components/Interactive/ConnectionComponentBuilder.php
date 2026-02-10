<?php

namespace App\Services\Components\Interactive;

class ConnectionComponentBuilder
{
    private string $token;
    private string $expiresAt;
    private int $minutesRemaining;
    private string $qrCodeUrl;
    private string $generateTokenRoute;
    private string $connectRoute;

    public function __construct(string $token, string $expiresAt, int $minutesRemaining)
    {
        $this->token = $token;
        $this->expiresAt = $expiresAt;
        $this->minutesRemaining = $minutesRemaining;
    }

    public function qrCodeUrl(string $url): self
    {
        $this->qrCodeUrl = $url;
        return $this;
    }

    public function generateTokenRoute(string $route): self
    {
        $this->generateTokenRoute = $route;
        return $this;
    }

    public function connectRoute(string $route): self
    {
        $this->connectRoute = $route;
        return $this;
    }

    public function build(): array
    {
        return [
            'type' => 'connection',
            'data' => [
                'token' => $this->token,
                'expiresAt' => $this->expiresAt,
                'minutesRemaining' => $this->minutesRemaining,
                'qrCodeUrl' => $this->qrCodeUrl ?? '',
                'generateTokenRoute' => $this->generateTokenRoute ?? '',
                'connectRoute' => $this->connectRoute ?? '',
            ]
        ];
    }
}
