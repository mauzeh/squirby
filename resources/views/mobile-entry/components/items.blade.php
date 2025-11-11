{{-- Items Component --}}
<section class="component-items-section" aria-label="{{ $data['ariaLabels']['section'] }}">
    @if($data['emptyMessage'] && empty($data['items']))
    <div class="component-items-empty">
        {{ $data['emptyMessage'] }}
    </div>
    @endif
    
    @foreach($data['items'] as $item)
    <div class="component-item">
        <div class="component-header">
            <h2 class="component-heading">{{ $item['title'] }}</h2>
            @if(isset($item['value']) && !empty($item['value']))
            <span class="component-value">{{ $item['value'] }}</span>
            @endif
            <div class="component-actions">
                <a href="{{ $item['editAction'] }}" class="btn-edit" aria-label="{{ $data['ariaLabels']['editItem'] }}">
                    <i class="fas fa-edit"></i>
                </a>
                <form class="delete-form" method="POST" action="{{ $item['deleteAction'] }}">
                    @csrf
                    @method('DELETE')
                    @if(isset($item['deleteParams']))
                        @foreach($item['deleteParams'] as $name => $value)
                            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                        @endforeach
                    @endif
                    <button type="submit" class="btn-delete" aria-label="{{ $data['ariaLabels']['deleteItem'] }}">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </div>
        </div>
        @if(isset($item['message']))
        <div class="component-message component-message--{{ $item['message']['type'] }}">
            @if(isset($item['message']['prefix']))
            <span class="message-prefix">{{ $item['message']['prefix'] }}}</span>
            @endif
            {{ $item['message']['text'] }}
        </div>
        @endif
        @if(isset($item['freeformText']) && !empty($item['freeformText']))
        <div class="component-freeform-text">
            {{ $item['freeformText'] }}
        </div>
        @endif
    </div>
    @endforeach
</section>

{{-- Pass confirm messages to JavaScript --}}
<script data-confirm-messages="{{ json_encode($data['confirmMessages']) }}"></script>
