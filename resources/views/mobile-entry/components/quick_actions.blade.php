{{-- Quick Actions Component --}}
<section class="component-form-section" data-initial-state="{{ $data['initialState'] ?? 'visible' }}">
    <div class="component-header">
        <h2 class="component-heading">{{ $data['title'] }}</h2>
    </div>
    <div class="component-body">
            <div class="quick-actions-grid">
                @foreach($data['actions'] as $action)
                    @if($action['type'] === 'form')
                        <form method="POST" action="{{ $action['action'] }}" class="quick-action-form">
                            @csrf
                            @if($action['method'] !== 'POST')
                                <input type="hidden" name="_method" value="{{ $action['method'] }}">
                            @endif
                            @foreach($action['params'] as $key => $value)
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endforeach
                            <button type="submit" 
                                    class="quick-action-btn {{ $action['cssClass'] }}{{ isset($action['disabled']) && $action['disabled'] ? ' btn-disabled' : '' }}"
                                    @if(isset($action['disabled']) && $action['disabled'])
                                        disabled
                                        @if(isset($action['disabledReason']) && $action['disabledReason'])
                                            title="{{ $action['disabledReason'] }}"
                                        @endif
                                    @else
                                        @if($action['confirm'])
                                            onclick="return confirm('{{ addslashes($action['confirm']) }}')"
                                        @endif
                                    @endif>
                                <i class="fa {{ $action['icon'] }}"></i>
                                <span class="quick-action-text">{{ $action['text'] }}</span>
                            </button>
                        </form>
                    @else
                        <a href="{{ $action['url'] }}" class="quick-action-btn {{ $action['cssClass'] }}">
                            <i class="fa {{ $action['icon'] }}"></i>
                            <span class="quick-action-text">{{ $action['text'] }}</span>
                        </a>
                    @endif
                @endforeach
            </div>
    </div>
</section>