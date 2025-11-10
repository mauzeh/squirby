{{-- Logged Items Component --}}
<section class="logged-items-section" aria-label="{{ $data['ariaLabels']['section'] }}">
    @if($data['emptyMessage'] && empty($data['items']))
    <div class="logged-item logged-items-empty">
        {{ $data['emptyMessage'] }}
    </div>
    @endif
    
    @foreach($data['items'] as $item)
    <div class="logged-item">
        <div class="item-header">
            <h2 class="item-title">{{ $item['title'] }}</h2>
            @if(isset($item['value']) && !empty($item['value']))
            <span class="item-value">{{ $item['value'] }}</span>
            @endif
            <div class="item-actions">
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
        <div class="item-message item-message--{{ $item['message']['type'] }}">
            @if(isset($item['message']['prefix']))
            <span class="message-prefix">{{ $item['message']['prefix'] }}</span>
            @endif
            {{ $item['message']['text'] }}
        </div>
        @endif
        @if(isset($item['freeformText']) && !empty($item['freeformText']))
        <div class="item-freeform-text">
            {{ $item['freeformText'] }}
        </div>
        @endif
    </div>
    @endforeach
</section>

{{-- Pass confirm messages to JavaScript --}}
<script data-confirm-messages="{{ json_encode($data['confirmMessages']) }}"></script>
