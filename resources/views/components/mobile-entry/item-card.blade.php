{{--
Mobile Entry Item Card Component

Unified card component with configurable actions and content slots.
Supports both lift program cards and food log cards with flexible content.

@param string $title - Card title/heading
@param string|null $deleteRoute - Route for delete action (optional)
@param string $deleteConfirmText - Confirmation text for delete action (default: 'Are you sure?')
@param array $hiddenFields - Hidden form fields for delete form (optional)
@param string|null $moveActions - HTML content for move up/down buttons (optional)
@param string|null $cardClass - Additional CSS classes for the card (optional)
@param bool $showActions - Whether to show the actions section (default: true)
--}}

@props([
    'title',
    'deleteRoute' => null,
    'deleteConfirmText' => 'Are you sure?',
    'hiddenFields' => [],
    'moveActions' => null,
    'cardClass' => '',
    'showActions' => true
])

<div class="program-card{{ $cardClass ? ' ' . $cardClass : '' }}">
    @if($showActions && ($deleteRoute || $moveActions))
        <div class="program-card-actions">
            {{-- Move actions (for lift program cards) --}}
            @if($moveActions)
                {!! $moveActions !!}
            @endif
            
            {{-- Delete action --}}
            @if($deleteRoute)
                <form action="{{ $deleteRoute }}" method="POST" onsubmit="return confirm('{{ $deleteConfirmText }}');">
                    @csrf
                    @method('DELETE')
                    
                    {{-- Hidden fields --}}
                    @foreach($hiddenFields as $name => $value)
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endforeach
                    
                    <button type="submit" class="program-action-button delete-program-button">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </form>
            @endif
        </div>
    @endif
    
    {{-- Card title --}}
    <h2>{{ $title }}</h2>
    
    {{-- Card content slot --}}
    {{ $slot }}
</div>