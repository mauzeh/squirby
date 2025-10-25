{{--
Mobile Entry Date Navigation Component

Provides prev/today/next navigation with Carbon date handling.
Consolidates identical date navigation markup from lift-logs and food-logs templates.

@param Carbon $selectedDate - The currently selected date
@param string $routeName - The route name for navigation links (e.g., 'lift-logs.mobile-entry')
@param array $routeParams - Additional route parameters (optional)
--}}

@props([
    'selectedDate',
    'routeName',
    'routeParams' => []
])

@php
    $today = \Carbon\Carbon::today();
    $prevDay = $selectedDate->copy()->subDay();
    $nextDay = $selectedDate->copy()->addDay();
    
    // Merge date parameter with any additional route parameters
    $prevParams = array_merge($routeParams, ['date' => $prevDay->toDateString()]);
    $todayParams = array_merge($routeParams, ['date' => $today->toDateString()]);
    $nextParams = array_merge($routeParams, ['date' => $nextDay->toDateString()]);
@endphp

<div class="date-navigation-mobile">
    <a href="{{ route($routeName, $prevParams) }}" class="nav-button">&lt; Prev</a>
    <a href="{{ route($routeName, $todayParams) }}" class="nav-button">Today</a>
    <a href="{{ route($routeName, $nextParams) }}" class="nav-button">Next &gt;</a>
</div>