{{-- Form Component --}}
<section class="component-form-section form{{ isset($data['cssClass']) ? ' ' . $data['cssClass'] : '' }}" aria-label="{{ $data['ariaLabels']['section'] }}"{{ $data['type'] ? ' data-form-type="' . $data['type'] . '"' : '' }} data-form-id="{{ $data['id'] }}"{{ isset($data['initialState']) ? ' data-initial-state="' . $data['initialState'] . '"' : '' }}>
    @if($data['title'] || $data['deleteAction'])
    <div class="component-header">
        @if($data['title'])
        <h2 class="component-heading">{{ $data['title'] }}</h2>
        @endif
        @if($data['deleteAction'])
        <form class="delete-form" method="POST" action="{{ $data['deleteAction'] }}">
            @csrf
            @method('DELETE')
            @if(isset($data['deleteParams']))
                @foreach($data['deleteParams'] as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
            @endif
            <button type="submit" class="btn btn-delete" aria-label="{{ $data['ariaLabels']['deleteForm'] }}">
                <i class="fas fa-trash"></i>
            </button>
        </form>
        @endif
    </div>
    @endif
    
    @if(!empty($data['messages']))
    <div class="component-messages">
        @foreach($data['messages'] as $message)
        <div class="component-message component-message--{{ $message['type'] }}">
            @if(isset($message['prefix']))
            <span class="message-prefix">{{ $message['prefix'] }}</span>
            @endif
            {{ $message['text'] }}
        </div>
        @endforeach
    </div>
    @endif
    
    <form class="component-form-element" method="POST" action="{{ $data['formAction'] }}" data-form-type="{{ $data['type'] }}" @if(isset($data['confirmMessage'])) data-confirm-message="{{ $data['confirmMessage'] }}" @endif>
        @csrf
        <input type="hidden" name="form_type" value="{{ $data['type'] }}">
        <input type="hidden" name="item_name" value="{{ $data['itemName'] }}">
        @foreach($data['hiddenFields'] as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        
        @if(!empty($data['sections']))
            {{-- Render sections --}}
            @foreach($data['sections'] as $section)
                <div class="form-section{{ $section['collapsible'] ? ' form-section-collapsible' : '' }}{{ $section['initialState'] === 'collapsed' ? ' collapsed' : ' expanded' }}" data-section-title="{{ $section['title'] }}">
                    <div class="form-section-header">
                        @if($section['collapsible'])
                            <i class="fas fa-chevron-right form-section-icon"></i>
                        @endif
                        <h3 class="form-section-title">{{ $section['title'] }}</h3>
                    </div>
                    
                    <div class="form-section-content">
                        @foreach($section['fields'] as $field)
                            @include('mobile-entry.components.form-field', ['field' => $field, 'data' => $data])
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif
        
        @foreach($data['numericFields'] as $field)
            @include('mobile-entry.components.form-field', ['field' => $field, 'data' => $data])
        @endforeach
        
        <div class="form-actions">
            <button type="submit" class="btn {{ $data['submitButtonClass'] ?? 'btn-primary' }}">{{ $data['buttons']['submit'] }}</button>
        </div>
    </form>
</section>
