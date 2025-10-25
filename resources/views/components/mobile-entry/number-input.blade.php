{{--
Mobile Entry Number Input Component

Number input with increment/decrement functionality and configurable step values.
Consolidates form input patterns from lift-logs and food-logs templates.

@param string $name - Input name attribute
@param string $id - Input id attribute
@param numeric $value - Input value (default: 1)
@param string $label - Input label text
@param string|null $unit - Unit display text (optional)
@param numeric $step - Increment/decrement step value (default: 1)
@param numeric $min - Minimum value (default: 0)
@param numeric|null $max - Maximum value (optional)
@param bool $required - Whether input is required (default: false)
@param string|null $inputClass - Additional CSS classes for input (optional)
@param string|null $groupClass - Additional CSS classes for form group (optional)
--}}

@props([
    'name',
    'id',
    'value' => 1,
    'label',
    'unit' => null,
    'step' => 1,
    'min' => 0,
    'max' => null,
    'required' => false,
    'inputClass' => '',
    'groupClass' => ''
])

<div class="form-group{{ $groupClass ? ' ' . $groupClass : '' }}">
    <label for="{{ $id }}" class="form-label-centered">{{ $label }}</label>
    <div class="input-group">
        <button type="button" class="decrement-button" data-target="{{ $id }}" data-step="{{ $step }}" data-min="{{ $min }}">-</button>
        <input 
            type="number" 
            name="{{ $name }}" 
            id="{{ $id }}" 
            class="large-input{{ $inputClass ? ' ' . $inputClass : '' }}"
            inputmode="decimal"
            value="{{ $value }}"
            step="{{ $step }}"
            min="{{ $min }}"
            @if($max !== null) max="{{ $max }}" @endif
            @if($required) required @endif
        >
        <button type="button" class="increment-button" data-target="{{ $id }}" data-step="{{ $step }}" data-max="{{ $max }}">+</button>
    </div>
    @if($unit)
        <span class="unit-display">{{ $unit }}</span>
    @endif
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Setup increment/decrement functionality for this specific input
    const incrementButton = document.querySelector('[data-target="{{ $id }}"].increment-button');
    const decrementButton = document.querySelector('[data-target="{{ $id }}"].decrement-button');
    const input = document.getElementById('{{ $id }}');
    
    if (incrementButton && decrementButton && input) {
        [incrementButton, decrementButton].forEach(button => {
            button.addEventListener('click', function() {
                const fieldId = this.dataset.target;
                const stepValue = parseFloat(this.dataset.step) || 1;
                const minValue = parseFloat(this.dataset.min) || 0;
                const maxValue = this.dataset.max ? parseFloat(this.dataset.max) : null;
                const isIncrement = this.classList.contains('increment-button');
                
                let currentValue = parseFloat(input.value) || 0;
                
                if (isIncrement) {
                    currentValue += stepValue;
                    if (maxValue !== null && currentValue > maxValue) {
                        currentValue = maxValue;
                    }
                } else {
                    currentValue -= stepValue;
                    if (currentValue < minValue) {
                        currentValue = minValue;
                    }
                }
                
                // Round to avoid floating point precision issues
                const decimalPlaces = stepValue < 1 ? 2 : 0;
                input.value = currentValue.toFixed(decimalPlaces).replace(/\.?0+$/, '');
                
                // Trigger input event for any listeners
                input.dispatchEvent(new Event('input', { bubbles: true }));
            });
        });
    }
});
</script>