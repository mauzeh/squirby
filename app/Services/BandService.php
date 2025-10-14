<?php

namespace App\Services;

class BandService
{
    public function getBands(): array
    {
        return config('bands.colors', []);
    }

    public function getBandResistance(string $color): ?int
    {
        return config('bands.colors.' . $color . '.resistance');
    }

    public function getNextHarderBand(string $currentColor, string $bandType): ?string
    {
        $bands = $this->getBands();
        $currentOrder = $bands[$currentColor]['order'] ?? null;

        if ($currentOrder === null) {
            return null;
        }

        $nextBand = null;
        if ($bandType === 'resistance') {
            $nextOrder = PHP_INT_MAX;
            foreach ($bands as $color => $data) {
                if ($data['order'] > $currentOrder && $data['order'] < $nextOrder) {
                    $nextOrder = $data['order'];
                    $nextBand = $color;
                }
            }
        } elseif ($bandType === 'assistance') {
            $nextOrder = PHP_INT_MAX;
            foreach ($bands as $color => $data) {
                if ($data['order'] < $currentOrder && $data['order'] > 0) {
                    $nextOrder = $data['order'];
                    $nextBand = $color;
                }
            }
        }

        return $nextBand;
    }

    public function getPreviousEasierBand(string $currentColor, string $bandType): ?string
    {
        $bands = $this->getBands();
        $currentOrder = $bands[$currentColor]['order'] ?? null;

        if ($currentOrder === null) {
            return null;
        }

        $previousBand = null;
        if ($bandType === 'resistance') {
            $previousOrder = 0;
            foreach ($bands as $color => $data) {
                if ($data['order'] < $currentOrder && $data['order'] > $previousOrder) {
                    $previousOrder = $data['order'];
                    $previousBand = $color;
                }
            }
        } elseif ($bandType === 'assistance') {
            $previousOrder = PHP_INT_MAX;
            foreach ($bands as $color => $data) {
                if ($data['order'] > $currentOrder && $data['order'] < $previousOrder) {
                    $previousOrder = $data['order'];
                    $previousBand = $color;
                }
            }
        }

        return $previousBand;
    }
}
