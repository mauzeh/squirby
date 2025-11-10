{{-- Item List Component --}}
<section class="item-selection-section" aria-label="{{ $data['ariaLabels']['section'] }}">
    <ul class="item-selection-list">
        <li>
            <div class="item-filter-container">
                <div class="item-filter-group">
                    <div class="item-filter-input-wrapper">
                        <input type="text" class="item-filter-input" placeholder="{{ $data['filterPlaceholder'] }}" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">
                        <button type="button" class="btn-clear-filter" aria-label="Clear filter" style="display: none;">×</button>
                    </div>
                    @if($data['createForm'])
                    <form method="{{ $data['createForm']['method'] }}" action="{{ $data['createForm']['action'] }}" class="create-item-form">
                        @csrf
                        <input type="hidden" name="{{ $data['createForm']['inputName'] }}" class="create-item-input" value="">
                        @foreach($data['createForm']['hiddenFields'] as $name => $value)
                            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                        @endforeach
                        <button type="submit" class="btn-secondary btn-create" aria-label="{{ $data['createForm']['ariaLabel'] }}">
                            <span class="plus-icon">{{ $data['createForm']['submitText'] }}</span>
                        </button>
                    </form>
                    @endif
                    <button type="button" class="btn-secondary btn-cancel" aria-label="Cancel and go back">
                        <span class="cancel-icon">×</span>
                    </button>
                </div>
            </div>
        </li>
        @foreach($data['items'] as $item)
        <li>
            <a href="{{ $item['href'] }}" class="item-selection-card item-selection-card--{{ $item['type']['cssClass'] }}" 
               aria-label="{{ $data['ariaLabels']['selectItem'] }}: {{ $item['name'] }}">
                <span class="item-name">{{ $item['name'] }}</span>
                <span class="item-type">{!! $item['type']['label'] !!}</span>
            </a>
        </li>
        @endforeach
        <li class="no-results-item" style="display: none;">
            <div class="item-selection-card item-selection-card--no-results">
                <span class="item-name">{{ $data['noResultsMessage'] }}</span>
                <span class="item-type">No matches</span>
            </div>
        </li>
    </ul>
</section>
