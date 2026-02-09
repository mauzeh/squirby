<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class MenuService
{
    /**
     * Processes an array of menu items, setting active states and handling roles.
     *
     * @param array $menuItems
     * @param ?string $currentRoute
     * @return array
     */
    private function processMenuItems(array $menuItems, ?string $currentRoute = null): array
    {
        if (is_null($currentRoute)) {
            $currentRoute = Request::route() ? Request::route()->getName() : null;
        }

        $processedItems = [];
        foreach ($menuItems as $item) {
            // Check visibility callback
            if (isset($item['visible']) && is_callable($item['visible'])) {
                if (!$item['visible']()) {
                    continue; // Skip this item if visibility callback returns false
                }
            }
            
            // Check roles
            if (isset($item['roles'])) {
                $hasRole = false;
                foreach ($item['roles'] as $role) {
                    if ($role === 'Impersonator') {
                        if (session()->has('impersonator_id')) {
                            $hasRole = true;
                            break;
                        }
                    } elseif (Auth::user() && Auth::user()->hasRole($role)) {
                        $hasRole = true;
                        break;
                    }
                }
                if (!$hasRole) {
                    continue; // Skip this item if user doesn't have required roles
                }
            }

            // Determine active state
            if (isset($item['patterns']) && $currentRoute) {
                $item['active'] = Request::routeIs($item['patterns']);
            } else {
                $item['active'] = false;
            }

            // Calculate badge count if badge callback is defined
            if (isset($item['badge']) && is_callable($item['badge'])) {
                $item['badgeCount'] = $item['badge']();
            }

            // Recursively process children
            if (isset($item['children'])) {
                $item['children'] = $this->processMenuItems($item['children'], $currentRoute);
            }

            $processedItems[] = $item;
        }
        return $processedItems;
    }

    /**
     * Get the main navigation menu items
     *
     * @return array
     */
    public function getMainMenu(): array
    {
        $config = config('menu');
        return $this->processMenuItems($config['main']);
    }

    /**
     * Get the right-side utility menu items
     *
     * @return array
     */
    public function getUtilityMenu(): array
    {
        $config = config('menu');
        return $this->processMenuItems($config['utility']);
    }

    /**
     * Get the sub-navigation menu items based on current route
     *
     * @return array|null
     */
    public function getSubMenu(): ?array
    {
        $mainMenuItems = $this->getMainMenu();
        $utilityMenuItems = $this->getUtilityMenu(); // Get utility menu items as well
        $subMenuItems = [];

        // Check main menu items for active status and children
        foreach ($mainMenuItems as $mainItem) {
            if (isset($mainItem['active']) && $mainItem['active'] && isset($mainItem['children'])) {
                $subMenuItems = $mainItem['children'];
                break;
            }
        }

        // If no active main menu item with children, check utility menu items
        if (empty($subMenuItems)) {
            foreach ($utilityMenuItems as $utilityItem) {
                if (isset($utilityItem['active']) && $utilityItem['active'] && isset($utilityItem['children'])) {
                    $subMenuItems = $utilityItem['children'];
                    break;
                }
            }
        }

        if (empty($subMenuItems)) {
            return null;
        }

        $finalSubMenuItems = [];
        foreach ($subMenuItems as $subItem) {
            if (isset($subItem['type']) && $subItem['type'] === 'dynamic-measurement-types') {
                // Handle dynamic measurement types for Body
                $measurementTypes = \App\Models\MeasurementType::where('user_id', Auth::id())
                    ->orderBy('name')
                    ->get();

                foreach ($measurementTypes as $measurementType) {
                    $finalSubMenuItems[] = [
                        'label' => $measurementType->name,
                        'route' => 'body-logs.show-by-type',
                        'routeParams' => [$measurementType],
                        'active' => Request::is('body-logs/type/' . $measurementType->id),
                    ];
                }
            } else {
                $finalSubMenuItems[] = $subItem;
            }
        }

        return $finalSubMenuItems;
    }

    /**
     * Check if sub-navigation should be shown
     *
     * @return bool
     */
    public function shouldShowSubMenu(): bool
    {
        return !empty($this->getSubMenu());
    }
}
