{{-- Form Component --}}
<section class="item-logging-section form" aria-label="{{ $data['ariaLabels']['section'] }}" data-form-type="{{ $data['type'] }}" data-form-id="{{ $data['id'] }}">
    <div class="item-header">
        <h2 class="item-title">{{ $data['title'] }}</h2>
        @if($data['deleteAction'])
        <form class="delete-form" method="POST" action="{{ $data['deleteAction'] }}">
            @csrf
            @method('DELETE')
            @if(isset($data['deleteParams']))
                @foreach($data['deleteParams'] as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
            @endif
            <button type="submit" class="btn-delete" aria-label="{{ $data['ariaLabels']['deleteForm'] }}">
                <i class="fas fa-trash"></i>
            </button>
        </form>
        @endif
    </div>
    
    @if(!empty($data['messages']))
    <div class="item-messages">
        @foreach($data['messages'] as $message)
        <div class="item-message item-message--{{ $message['type'] }}">
            @if(isset($message['prefix']))
            <span class="message-prefix">{{ $message['prefix'] }}</span>
            @endif
            {{ $message['text'] }}
        </div>
        @endforeach
    </div>
    @endif
    
    <form class="item-form" method="POST" action="{{ $data['formAction'] }}" data-form-type="{{ $data['type'] }}">
        @csrf
        <input type="hidden" name="form_type" value="{{ $data['type'] }}">
        <input type="hidden" name="item_name" value="{{ $data['itemName'] }}">
        @foreach($data['hiddenFields'] as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        
        @foreach($data['numericFields'] as $field)
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
        @endforeach
        
        <div class="form-mobile-group">
            <label for="{{ $data['commentField']['id'] }}" class="form-mobile-label">{{ $data['commentField']['label'] }}</label>
            <textarea id="{{ $data['commentField']['id'] }}" name="{{ $data['commentField']['name'] }}" class="comment-textarea" placeholder="{{ $data['commentField']['placeholder'] }}" rows="3">{{ old($data['commentField']['name'], $data['commentField']['defaultValue']) }}</textarea>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn-primary">{{ $data['buttons']['submit'] }}</button>
        </div>
    </form>
</section>
