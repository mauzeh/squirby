{{--
Mobile Entry Page Title Component

Displays conditional date title with Today/Yesterday/Tomorrow/formatted date handling.
Consolidates identical page title logic from lift-logs and food-logs templates.

@param Carbon $selectedDate - The currently selected date
@param string $tag - HTML tag to use for title (default: 'h1')
@param string $class - Additional CSS classes (optional)
--}}

@props([
    'selectedDate',
    'tag' => 'h1',
    'class' => ''
])

@php
    $titleText = '';
    
    if ($selectedDate->isToday()) {
        $titleText = 'Today';
    } elseif ($selectedDate->isYesterday()) {
        $titleText = 'Yesterday';
    } elseif ($selectedDate->isTomorrow()) {
        $titleText = 'Tomorrow';
    } else {
        $titleText = $selectedDate->format('M d, Y');
    }
@endphp

<{{ $tag }}{{ $class ? ' class="' . $class . '"' : '' }}>
    {{ $titleText }}
</{{ $tag }}>