{{-- Item List Component --}}
<section class="component-list-section{{ ($data['initialState'] ?? 'collapsed') === 'expanded' ? ' active' : '' }}" aria-label="{{ $data['ariaLabels']['section'] }}" data-initial-state="{{ $data['initialState'] ?? 'collapsed' }}">
    {{-- Sticky Filter Header --}}
    <div class="component-filter-container">
        <div class="component-filter-group{{ ($data['showCancelButton'] ?? true) ? '' : ' component-filter-group--no-cancel' }}">
            <div class="component-filter-input-wrapper">
                <i class="fas fa-search search-icon" aria-hidden="true"></i>
                <input type="text" class="component-filter-input" placeholder="{{ $data['filterPlaceholder'] }}" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">
                <button type="button" class="btn-clear-filter" aria-label="Clear filter" style="display: none;">×</button>
            </div>
            @if($data['showCancelButton'] ?? true)
            <button type="button" class="btn btn-secondary btn-cancel" aria-label="Cancel and go back">
                <span class="cancel-icon">×</span>
            </button>
            @endif
        </div>
    </div>
    
    {{-- Scrollable Item List --}}
    <ul class="component-list{{ ($data['restrictHeight'] ?? true) ? '' : ' component-list--no-height-restriction' }}">
        @foreach($data['items'] as $item)
        <li>
            <a href="{{ $item['href'] }}" class="component-list-item component-list-item--{{ $item['type']['cssClass'] }}" 
               aria-label="{{ $data['ariaLabels']['selectItem'] }}: {{ $item['name'] }}">
                <span class="component-list-item-name">{{ $item['name'] }}</span>
                @if(!empty($item['type']['label']))
                <span class="component-list-item-type">{!! $item['type']['label'] !!}</span>
                @endif
            </a>
        </li>
        @endforeach
        <li class="no-results-item" style="display: none;">
            <div class="component-list-item component-list-item--no-results">
                <span class="component-list-item-name">{{ $data['noResultsMessage'] }}</span>
                <span class="component-list-item-type">No matches</span>
            </div>
        </li>
        @if($data['createForm'])
        <li class="create-item-li" style="display: none;">
            <form method="{{ $data['createForm']['method'] }}" action="{{ $data['createForm']['action'] }}" class="component-create-form">
                @if(strtoupper($data['createForm']['method']) !== 'GET')
                    @csrf
                @endif
                <input type="hidden" name="{{ $data['createForm']['inputName'] }}" class="component-create-input" value="">
                @foreach($data['createForm']['hiddenFields'] as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
                <button type="submit" class="btn btn-primary btn-create" aria-label="{{ $data['createForm']['ariaLabel'] }}" data-text-template="{{ $data['createForm']['buttonTextTemplate'] ?? 'Create "{term}"' }}">
                    <span class="btn-create-text">{{ $data['createForm']['submitText'] }}</span>
                </button>
            </form>
        </li>
        @endif
    </ul>
</section>
