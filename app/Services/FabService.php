<?php

namespace App\Services;

use App\Models\User;
use App\Services\ComponentBuilder;

/**
 * Service for creating FAB (Floating Action Button) components
 * Centralizes FAB creation logic and tooltip display rules
 */
class FabService
{
    /**
     * Create a connection FAB for the given user
     * 
     * @param User $user The user to create the FAB for
     * @param string|null $context The context where the FAB is being shown ('feed', 'lifts', etc.)
     * @return array The FAB component data
     */
    public function createConnectionFab(User $user, ?string $context = null): array
    {
        $showTooltip = $this->shouldShowTooltip($user, $context);
        
        $fab = ComponentBuilder::fab(route('connections.index'), 'fa-user-plus')
            ->title('Connect');
        
        if ($showTooltip) {
            $fab->tooltip('Connect with friends');
        }
        
        return $fab->build();
    }
    
    /**
     * Determine if the tooltip should be shown based on context and user state
     * 
     * @param User $user The user to check
     * @param string|null $context The context where the FAB is being shown
     * @return bool True if tooltip should be shown, false otherwise
     */
    private function shouldShowTooltip(User $user, ?string $context): bool
    {
        // Always show tooltip on Feed pages (where connections matter most)
        if ($context === 'feed') {
            return true;
        }
        
        // On Lifts page, show only if they have no connections AND have a PR today
        if ($context === 'lifts') {
            $hasNonAdminConnections = $this->hasNonAdminConnections($user);
            
            if ($hasNonAdminConnections) {
                return false;
            }
            
            // Check if user has any PRs today (timely moment to suggest connecting)
            $hasPRToday = $user->personalRecords()
                ->current()
                ->whereDate('achieved_at', today())
                ->exists();
            
            return $hasPRToday;
        }
        
        // Default behavior for other contexts: show if no connections
        return !$this->hasNonAdminConnections($user);
    }
    
    /**
     * Check if user has any non-admin connections (following or followers)
     * Excludes admin users since they're often in networks by default
     * 
     * @param User $user The user to check
     * @return bool True if user has non-admin connections, false otherwise
     */
    private function hasNonAdminConnections(User $user): bool
    {
        // Check if user is following any non-admin users
        $hasNonAdminFollowing = $user->following()
            ->whereDoesntHave('roles', function ($query) {
                $query->where('name', 'Admin');
            })
            ->exists();
        
        if ($hasNonAdminFollowing) {
            return true;
        }
        
        // Check if user has any non-admin followers
        $hasNonAdminFollowers = $user->followers()
            ->whereDoesntHave('roles', function ($query) {
                $query->where('name', 'Admin');
            })
            ->exists();
        
        return $hasNonAdminFollowers;
    }
}
