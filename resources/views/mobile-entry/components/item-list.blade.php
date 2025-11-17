{{-- Item List Component --}}
<section class="component-list-section{{ ($data['initialState'] ?? 'collapsed') === 'expanded' ? ' active' : '' }}" aria-label="{{ $data['ariaLabels']['section'] }}" data-initial-state="{{ $data['initialState'] ?? 'collapsed' }}">
    <ul class="component-list">
        <li>
            <div class="component-filter-container">
                <div class="component-filter-group">
                    <div class="component-filter-input-wrapper">
                        <input type="text" class="component-filter-input" placeholder="{{ $data['filterPlaceholder'] }}" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">
                        <button type="button" class="btn-clear-filter" aria-label="Clear filter" style="display: none;">×</button>
                    </div>
                    <button type="button" class="btn-secondary btn-cancel" aria-label="Cancel and go back">
                        <span class="cancel-icon">×</span>
                    </button>
                </div>
            </div>
        </li>
        @foreach($data['items'] as $item)
        <li>
            <a href="{{ $item['href'] }}" class="component-list-item component-list-item--{{ $item['type']['cssClass'] }}" 
               aria-label="{{ $data['ariaLabels']['selectItem'] }}: {{ $item['name'] }}">
                <span class="component-list-item-name">{{ $item['name'] }}</span>
                <span class="component-list-item-type">{!! $item['type']['label'] !!}</span>
            </a>
        </li>
        @endforeach
        <li class="no-results-item" style="display: none;">
            <div class="component-list-item component-list-item--no-results">
                <span class="component-list-item-name">{{ $data['noResultsMessage'] }}</span>
                <span class="component-list-item-type">No matches</span>
            </div>
            @if($data['createForm'])
            <form method="{{ $data['createForm']['method'] }}" action="{{ $data['createForm']['action'] }}" class="component-create-form">
                @if(strtoupper($data['createForm']['method']) !== 'GET')
                    @csrf
                @endif
                <input type="hidden" name="{{ $data['createForm']['inputName'] }}" class="component-create-input" value="">
                @foreach($data['createForm']['hiddenFields'] as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
                <button type="submit" class="btn-primary btn-create" aria-label="{{ $data['createForm']['ariaLabel'] }}" data-text-template="{{ $data['createForm']['buttonTextTemplate'] ?? 'Create "{term}"' }}">
                    <span class="btn-create-text">{{ $data['createForm']['submitText'] }}</span>
                </button>
            </form>
            @endif
        </li>
    </ul>
</section>
