{{-- Form Field Partial --}}
<div class="form-mobile-group">
    <label for="{{ $field['id'] }}" class="form-mobile-label">{{ $field['label'] }}</label>
    @if(isset($field['type']) && $field['type'] === 'select')
        <select id="{{ $field['id'] }}" name="{{ $field['name'] }}" class="form-select" aria-label="{{ $field['ariaLabels']['field'] }}">
            @foreach($field['options'] as $option)
                <option value="{{ $option['value'] }}" {{ old($field['name'], $field['defaultValue']) == $option['value'] ? 'selected' : '' }}>
                    {{ $option['label'] }}
                </option>
            @endforeach
        </select>
    @elseif(isset($field['type']) && $field['type'] === 'text')
        <input type="text" id="{{ $field['id'] }}" name="{{ $field['name'] }}" class="text-input" value="{{ old($field['name'], $field['defaultValue']) }}" placeholder="{{ $field['placeholder'] ?? '' }}" aria-label="{{ $field['ariaLabels']['field'] }}">
    @elseif(isset($field['type']) && $field['type'] === 'textarea')
        <textarea id="{{ $field['id'] }}" name="{{ $field['name'] }}" class="comment-textarea" placeholder="{{ $field['placeholder'] ?? '' }}" rows="3" aria-label="{{ $field['ariaLabels']['field'] }}">{{ old($field['name'], $field['defaultValue']) }}</textarea>
    @else
        <div class="number-input-group" 
             data-increment="{{ $field['increment'] }}" 
             data-min="{{ $field['min'] }}" 
             data-max="{{ $field['max'] ?? '' }}">
            <button type="button" class="decrement-button" aria-label="{{ $field['ariaLabels']['decrease'] }}">{{ $data['buttons']['decrement'] }}</button>
            <input type="number" id="{{ $field['id'] }}" name="{{ $field['name'] }}" class="number-input" value="{{ old($field['name'], $field['defaultValue']) }}" min="{{ $field['min'] }}" step="{{ $field['step'] ?? $field['increment'] }}" @if(isset($field['max'])) max="{{ $field['max'] }}" @endif>
            <button type="button" class="increment-button" aria-label="{{ $field['ariaLabels']['increase'] }}">{{ $data['buttons']['increment'] }}</button>
        </div>
    @endif
</div>
