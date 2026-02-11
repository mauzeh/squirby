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
     * @return array The FAB component data
     */
    public function createConnectionFab(User $user): array
    {
        $hasConnections = $this->hasConnections($user);
        
        $fab = ComponentBuilder::fab(route('connections.index'), 'fa-user-plus')
            ->title('Connect');
        
        if (!$hasConnections) {
            $fab->tooltip('Connect with friends');
        }
        
        return $fab->build();
    }
    
    /**
     * Check if user has any connections (following or followers)
     * 
     * @param User $user The user to check
     * @return bool True if user has connections, false otherwise
     */
    public function hasConnections(User $user): bool
    {
        return $user->following()->exists() || $user->followers()->exists();
    }
}
