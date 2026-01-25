<?php

namespace App\Services\LiftLogTableRowBuilder;

use App\Models\LiftLog;

/**
 * Builder for creating badge collections
 * Provides fluent API for adding badges to a row
 */
class BadgeCollectionBuilder
{
    private array $badges = [];
    
    /**
     * Add a date badge (Today, Yesterday, X days ago, or date)
     */
    public function addDateBadge(LiftLog $liftLog): self
    {
        $dateBadge = DateBadgeFormatter::format($liftLog);
        $this->badges[] = [
            'text' => $dateBadge['text'],
            'colorClass' => $dateBadge['color']
        ];
        return $this;
    }
    
    /**
     * Add a PR badge
     */
    public function addPRBadge(): self
    {
        $this->badges[] = [
            'text' => '🏆 PR',
            'colorClass' => 'pr'
        ];
        return $this;
    }
    
    /**
     * Add a reps/sets badge
     */
    public function addRepsBadge(string $repsSets): self
    {
        $this->badges[] = [
            'text' => $repsSets,
            'colorClass' => 'info'
        ];
        return $this;
    }
    
    /**
     * Add a weight badge
     */
    public function addWeightBadge(string $weight): self
    {
        $this->badges[] = [
            'text' => $weight,
            'colorClass' => 'success',
            'emphasized' => true
        ];
        return $this;
    }
    
    /**
     * Build and return the badges array
     */
    public function build(): array
    {
        return $this->badges;
    }
}
