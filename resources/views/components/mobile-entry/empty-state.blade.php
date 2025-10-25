{{--
Mobile Entry Empty State Component

Consistent messaging for when no items are available.
Consolidates empty state patterns from lift-logs and food-logs templates.

@param string $message - The empty state message to display
@param string|null $class - Additional CSS classes (optional)
@param string|null $actionText - Optional action button text
@param string|null $actionUrl - Optional action button URL
@param string|null $actionId - Optional action button ID for JavaScript handling
--}}

@props([
    'message',
    'class' => '',
    'actionText' => null,
    'actionUrl' => null,
    'actionId' => null
])

<div class="no-program-message{{ $class ? ' ' . $class : '' }}">
    <p>{{ $message }}</p>
    
    @if($actionText)
        @if($actionUrl)
            <a href="{{ $actionUrl }}" class="button-large button-blue">{{ $actionText }}</a>
        @elseif($actionId)
            <button type="button" id="{{ $actionId }}" class="button-large button-blue">{{ $actionText }}</button>
        @else
            <button type="button" class="button-large button-blue">{{ $actionText }}</button>
        @endif
    @endif
</div>