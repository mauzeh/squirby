{{--
Mobile Entry Add Item Button Component

Standardized button for adding exercises, food items, etc. with configurable behavior.
Consolidates add button patterns from lift-logs and food-logs templates.

@param string $id - Button id attribute
@param string $label - Button text label
@param string|null $targetContainer - ID of container to show when clicked (optional)
@param string|null $buttonClass - Additional CSS classes for button (optional)
@param string|null $containerClass - Additional CSS classes for container (optional)
@param bool $hideOnClick - Whether to hide button when clicked (default: true)
--}}

@props([
    'id',
    'label',
    'targetContainer' => null,
    'buttonClass' => '',
    'containerClass' => '',
    'hideOnClick' => true
])

<div class="add-exercise-container{{ $containerClass ? ' ' . $containerClass : '' }}">
    <button 
        type="button" 
        id="{{ $id }}" 
        class="button-large button-green{{ $buttonClass ? ' ' . $buttonClass : '' }}"
        @if($targetContainer) data-target-container="{{ $targetContainer }}" @endif
        @if($hideOnClick) data-hide-on-click="true" @endif
    >
        {{ $label }}
    </button>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const button = document.getElementById('{{ $id }}');
    
    if (button) {
        button.addEventListener('click', function() {
            const targetContainer = this.dataset.targetContainer;
            const hideOnClick = this.dataset.hideOnClick === 'true';
            
            // Show target container if specified
            if (targetContainer) {
                const container = document.getElementById(targetContainer);
                if (container) {
                    container.classList.remove('hidden');
                }
            }
            
            // Hide button if configured to do so
            if (hideOnClick) {
                this.style.display = 'none';
            }
            
            // Trigger custom event for additional handling
            this.dispatchEvent(new CustomEvent('addItemClicked', {
                bubbles: true,
                detail: { 
                    buttonId: this.id,
                    targetContainer: targetContainer 
                }
            }));
        });
    }
});
</script>